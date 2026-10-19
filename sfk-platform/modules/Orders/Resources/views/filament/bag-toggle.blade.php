{{-- 製 袋・製 本・他 加 工: folds the bag-making fields (legacy toggle script, open at first); `bagOpen` lives in the section's x-data. --}}
<button
    type="button"
    class="sfk-order-toggle"
    x-on:click="bagOpen = ! bagOpen"
    x-bind:aria-expanded="bagOpen ? 'true' : 'false'"
    x-text="bagOpen ? '▲ 折りたたむ' : '▼ 展開する'"
>▲ 折りたたむ</button>
