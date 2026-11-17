"""
Fills the 2026 catalog products that had no photo, using images from
assets/Catálogo NATUFITNESS 2026.pdf picked and checked by eye (page, image xref).
Also fixes names the automatic extraction got wrong (shared brand pages).

Writes 1080x1080 black-background photos to scripts/catalog-2026/photos/ and updates
scripts/catalog-2026/catalog.json. Then load them with scripts/import-catalog-2026.php.

Usage (needs PyMuPDF and Pillow):
    python3 -m venv .venv && .venv/bin/pip install pymupdf pillow
    .venv/bin/python tools/catalog/fill_missing_photos_2026.py
"""
import json
import pathlib
import re
import unicodedata

import pymupdf
from PIL import Image, ImageDraw, ImageFilter

ROOT = pathlib.Path(__file__).resolve().parents[2]
PDF = ROOT / 'assets' / 'Catálogo NATUFITNESS 2026.pdf'
CATALOG = ROOT / 'scripts' / 'catalog-2026' / 'catalog.json'
PHOTOS = ROOT / 'scripts' / 'catalog-2026' / 'photos'

SIZE, FIT = 1080, 900  # Full HD square, product fills ~83%

# reference -> (page, image xref), checked by eye against the rendered pages.
PHOTO_MAP = {
    'C26-ronnie-coleman-bcaa-xs': (2, 2285),
    'C26-ronnie-coleman-glutamina-xs': (2, 2300),
    'C26-beverly-nutrition-energy-storm': (4, 2557),
    'C26-beverly-nutrition-pure-whey': (4, 2550),
    'C26-carnivor-bebida-de-protena-40-g': (5, 2714),
    'C26-carnivor-shred': (5, 2750),
    'C26-kevin-levrone-bcaa-defender': (7, 2912),
    'C26-kevin-levrone-creatina-micronizada': (7, 2937),
    'C26-dorian-yates-shadowhey-isolate': (10, 3281),
    'C26-dorian-yates-shado-whey': (10, 3311),
    'C26-bpi-sports-one-more-rep': (12, 3611),
    'C26-bpi-sports-vegan-protein': (12, 3660),
    'C26-allmax-creatina-micronizada': (17, 831),
    'C26-nutrex-lipo-6-pre-entreno': (18, 4288),
    'C26-nutrex-whey-protein': (19, 4377),
    'C26-gat-sport-pre-workout': (21, 4693),
    'C26-gat-sport-testrol-gold': (21, 4698),
    'C26-gat-sport-creatina-gomas': (21, 1047),
    'C26-dragon-pharma-creatina-saborizada': (22, 1098),
    'C26-dymatize-pre-work': (24, 1197),
    'C26-sascha-fitness-collagen': (27, 5275),
    'C26-windmill-picolinato-de-cromo': (28, 5336),
    'C26-windmill-glicinato-de-magnesio': (28, 1347),
    'C26-windmill-cal-mag-zinc': (28, 5390),
    'C26-crema-de-arroz': (30, 1427),
    'C26-bum-geles-energticos': (30, 1428),
    'C26-bum-bebida-energtica': (30, 1421),
    'C26-optimum-nutrition-bebida-energizante': (31, 5646),
    'C26-mezcladores-shaker': (32, 5696),
}

# reference -> (name, brand): the photo shows another brand than the page header.
RENAMES = {
    'C26-gat-sport-creatina-gomas': ('Beast Bites Creatina en Gomas', 'Beast Bites'),
    'C26-dymatize-pre-work': ('Optimum Nutrition Gold Standard Pre-Workout', 'Optimum Nutrition'),
    'C26-optimum-nutrition-bebida-energizante': ('Ghost Bebida Energizante 473 ml', 'Ghost'),
    'C26-bum-geles-energticos': ('Geles Energéticos', ''),
    'C26-beverly-nutrition-pure-whey': ('Beverly Nutrition Pure Whey Vegan', 'Beverly Nutrition'),
}


def slug(text):
    ascii_text = unicodedata.normalize('NFKD', text).encode('ascii', 'ignore').decode().lower()
    return re.sub(r'[^a-z0-9]+', '-', ascii_text).strip('-')


def extract(doc, page_no, xref):
    page = doc[page_no - 1]
    smask = {im[0]: im[1] for im in page.get_images(full=True)}.get(xref, 0)
    pix = pymupdf.Pixmap(doc, xref)
    if smask and pix.alpha == 0:
        pix = pymupdf.Pixmap(pix, pymupdf.Pixmap(doc, smask))
    if pix.n - pix.alpha >= 4:
        pix = pymupdf.Pixmap(pymupdf.csRGB, pix)
    mode = 'RGBA' if pix.alpha else 'RGB'
    return Image.frombytes(mode, (pix.width, pix.height), pix.samples).convert('RGBA')


def on_black(im):
    """Product centred on black; white backgrounds are cut away from the corners."""
    if im.getchannel('A').getextrema()[0] == 255:
        rgb = im.convert('RGB')
        marked = rgb.copy()
        w, h = im.size
        for corner in [(0, 0), (w - 1, 0), (0, h - 1), (w - 1, h - 1)]:
            if sum(rgb.getpixel(corner)) > 690:
                ImageDraw.floodfill(marked, corner, (255, 0, 255), thresh=40)
        mask = Image.new('L', im.size, 0)
        px, m = marked.load(), mask.load()
        for y in range(h):
            for x in range(w):
                if px[x, y] != (255, 0, 255):
                    m[x, y] = 255
        im.putalpha(mask.filter(ImageFilter.GaussianBlur(0.8)))
    box = im.getchannel('A').point(lambda v: 255 if v > 16 else 0).getbbox() or (0, 0, *im.size)
    im = im.crop(box)
    scale = min(FIT / im.width, FIT / im.height)
    im = im.resize((max(1, round(im.width * scale)), max(1, round(im.height * scale))), Image.LANCZOS)
    if scale > 1.2:  # small sources: recover some edge definition after upscaling
        im = im.filter(ImageFilter.UnsharpMask(radius=2, percent=60, threshold=2))
    canvas = Image.new('RGB', (SIZE, SIZE), 'black')
    canvas.paste(im, ((SIZE - im.width) // 2, (SIZE - im.height) // 2), im)
    return canvas


def main():
    doc = pymupdf.open(PDF)
    items = json.loads(CATALOG.read_text())
    by_ref = {item['reference']: item for item in items}
    unknown = sorted(set(PHOTO_MAP) - set(by_ref))
    if unknown:
        raise SystemExit(f'Unknown references: {unknown}')

    for ref, (name, brand) in RENAMES.items():
        by_ref[ref]['name'], by_ref[ref]['brand'] = name, brand
    for ref, (page_no, xref) in PHOTO_MAP.items():
        item = by_ref[ref]
        filename = slug(item['name'])[:60] + '.jpg'
        on_black(extract(doc, page_no, xref)).save(PHOTOS / filename, quality=90, optimize=True, progressive=True)
        item['photo'] = filename

    CATALOG.write_text(json.dumps(items, ensure_ascii=False, indent=1))
    missing = [item['name'] for item in items if not item['photo']]
    print(f'{len(PHOTO_MAP)} photos written; products without photo: {len(missing)} {missing}')


if __name__ == '__main__':
    main()
