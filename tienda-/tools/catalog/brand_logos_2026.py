"""
Brand logos for the 2026 catalog brands, taken from the header banner of each brand's
page in assets/Catálogo NATUFITNESS 2026.pdf (regions checked by eye), saved as
400x200 tiles in themes/natufitness/assets/img/brands/<slug>.jpg.
scripts/setup-brands.php then installs them as PrestaShop brand logos.

Usage (needs PyMuPDF and Pillow):
    .venv/bin/python tools/catalog/brand_logos_2026.py
"""
import pathlib
from collections import Counter

import pymupdf
from PIL import Image

ROOT = pathlib.Path(__file__).resolve().parents[2]
PDF = ROOT / 'assets' / 'Catálogo NATUFITNESS 2026.pdf'
OUT = ROOT / 'themes' / 'natufitness' / 'assets' / 'img' / 'brands'
W, H = 400, 200
PAD = 12

# slug -> (page, clip rect in PDF points (x0, y0, x1, y1)) or (page, ('xref', n))
SOURCES = {
    'carnivor': (5, (0, 0, 810, 330)),
    'kevin-levrone': (7, (0, 80, 560, 330)),
    'evl-nutrition': (9, (0, 0, 810, 330)),
    'dorian-yates': (10, (0, 0, 810, 290)),
    'insane-labz': (11, (0, 0, 810, 330)),
    'integralmedica': (13, (0, 0, 810, 290)),
    'prosupps': (14, (0, 0, 810, 330)),
    'bum': (15, (0, 0, 810, 270)),
    'muscletech': (16, (0, 0, 810, 270)),
    'allmax': (17, (0, 0, 810, 230)),
    'nutrex': (18, (0, 0, 810, 330)),
    'biotechusa': (20, (0, 0, 810, 330)),
    'gat-sport': (21, (0, 0, 810, 270)),
    'dragon-pharma': (22, (0, 0, 810, 270)),
    'dymatize': (24, (0, 30, 400, 230)),
    'redcon1': (25, (0, 0, 810, 290)),
    'fa-nutrition': (26, (0, 0, 810, 300)),
    'windmill': (28, (0, 0, 810, 300)),
    'quest': (29, (0, 0, 810, 300)),
    'maximum': (30, (0, 0, 450, 110)),
    'beast-bites': (21, ('xref', 1048)),
}


# Brands whose page banner has no clean logo: official logo files in assets/logos-marcas/.
OFFICIAL = {
    'ronnie-coleman': 'ronnie-coleman.png',  # ronniecoleman.net site icon
    'ghost': 'ghost.png',  # ghostlifestyle.com logo
}


def official(filename):
    im = Image.open(ROOT / 'assets' / 'logos-marcas' / filename).convert('RGBA')
    canvas = Image.new('RGB', im.size, 'white')
    canvas.paste(im, (0, 0), im)
    return canvas


def render(doc, page_no, source):
    page = doc[page_no - 1]
    if source[0] == 'xref':
        pix = pymupdf.Pixmap(doc, source[1])
        if pix.n - pix.alpha >= 4:
            pix = pymupdf.Pixmap(pymupdf.csRGB, pix)
        return Image.frombytes('RGBA' if pix.alpha else 'RGB', (pix.width, pix.height), pix.samples).convert('RGB')
    pix = page.get_pixmap(dpi=200, clip=pymupdf.Rect(*source))
    return Image.frombytes('RGB', (pix.width, pix.height), pix.samples)


def tile(im):
    """Fit the banner into 400x200, filling the margins with its own border colour."""
    border = [im.getpixel((x, y)) for x in range(0, im.width, 7) for y in (0, im.height - 1)]
    border += [im.getpixel((x, y)) for y in range(0, im.height, 7) for x in (0, im.width - 1)]
    bg = Counter((r // 16 * 16, g // 16 * 16, b // 16 * 16) for r, g, b in border).most_common(1)[0][0]
    im = im.copy()
    im.thumbnail((W - 2 * PAD, H - 2 * PAD), Image.LANCZOS)
    canvas = Image.new('RGB', (W, H), bg)
    canvas.paste(im, ((W - im.width) // 2, (H - im.height) // 2))
    return canvas


def main():
    doc = pymupdf.open(PDF)
    for slug, (page_no, source) in SOURCES.items():
        tile(render(doc, page_no, source)).save(OUT / f'{slug}.jpg', quality=90)
    for slug, filename in OFFICIAL.items():
        tile(official(filename)).save(OUT / f'{slug}.jpg', quality=90)
    print(f'{len(SOURCES) + len(OFFICIAL)} logos written to {OUT.relative_to(ROOT)}')


if __name__ == '__main__':
    main()
