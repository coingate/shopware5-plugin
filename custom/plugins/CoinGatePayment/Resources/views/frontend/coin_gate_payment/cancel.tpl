{extends file='frontend/index/index.tpl'}

{* Main content *}
{block name='frontend_index_content'}
    <div class="example-content content custom-page--content">
        <div class="example-content--actions">
            <a class="btn"
               href="{url controller=checkout action=cart}"
               title="Change cart">Change cart
            </a>
            <a class="btn is--primary right"
               href="{url controller=checkout action=shippingPayment sTarget=checkout}"
               title="Change payment method">Change payment method
            </a>
        </div>
    </div>
{/block}

{block name='frontend_index_actions'}{/block}
