<section class="featured-products clearfix">


{foreach from=$products item="product"}

  {if $product.0 eq "texto"}
  <div>
    {$product.1 nofilter}
  </div>

  {else}
  <div class="products">
    {foreach from=$product.1 item="product2"}
      {include file="catalog/_partials/miniatures/product.tpl" product=$product2}
    {/foreach}
  </div>
  {/if}

  {/foreach}





</section>
