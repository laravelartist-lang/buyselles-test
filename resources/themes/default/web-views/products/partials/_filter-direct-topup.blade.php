@if($web_config['digital_product_setting'])
    <div class="">
        <h6 class="font-semibold fs-13 mb-2">{{ translate('Direct_Top_Up') }}</h6>
        <div class="d-flex align-items-center gap-2">
            <label class="switcher">
                <input class="switcher_input product-list-filter-input"
                       type="checkbox"
                       name="direct_topup"
                       value="1"
                       @if(request('direct_topup') == '1') checked @endif>
                <span class="switcher_control"></span>
            </label>
            <span class="fs-13 opacity-75">{{ translate('show_only_direct_top_up_products') }}</span>
        </div>
    </div>
@endif
