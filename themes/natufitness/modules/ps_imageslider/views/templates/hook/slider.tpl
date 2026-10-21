{*
 * NatuFitness hero carousel (syf.ec style) fed by ps_imageslider.
 * Each slide: image (cover), legend as the small line, title, optional description and a
 * "VER MÁS" pill to the slide URL. Edit in Módulos > Image slider > Configurar.
 * Behaviour lives in assets/js/custom.js (.js-nf-hero); the interval comes from the module speed.
 *}
{if $homeslider.slides}
  <section class="nf-hero js-nf-hero" data-interval="{$homeslider.speed}" data-pause="{$homeslider.pause}" aria-roledescription="carrusel" aria-label="Destacados">
    {foreach from=$homeslider.slides item=slide name=nf_slides}
      <div class="nf-hero__slide{if $smarty.foreach.nf_slides.first} is-active{/if}">
        <img class="nf-hero__image" src="{$slide.image_url}" alt="{$slide.legend}"
          {if !empty($slide.sizes)}width="{$slide.sizes[0]}" height="{$slide.sizes[1]}"{/if}
          {if $smarty.foreach.nf_slides.first}fetchpriority="high"{else}loading="lazy"{/if}>
        {if $slide.title || $slide.legend || $slide.url}
          <div class="nf-hero__text">
            {if $slide.legend}<small>{$slide.legend}</small>{/if}
            {if $slide.title}<p class="nf-hero__title">{$slide.title}</p>{/if}
            {if $slide.description|strip_tags|trim}<div class="nf-hero__desc">{$slide.description nofilter}</div>{/if}
            {if $slide.url}<a class="nf-btn-dark" href="{$slide.url}">VER MÁS ›</a>{/if}
          </div>
        {/if}
      </div>
    {/foreach}
    {if $homeslider.slides|count > 1}
      <button type="button" class="nf-hero__arrow nf-hero__arrow--prev js-nf-hero-prev" aria-label="Anterior">‹</button>
      <button type="button" class="nf-hero__arrow nf-hero__arrow--next js-nf-hero-next" aria-label="Siguiente">›</button>
      <div class="nf-hero__dots js-nf-hero-dots"></div>
    {/if}
  </section>
{/if}
