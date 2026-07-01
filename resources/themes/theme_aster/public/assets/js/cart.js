"use strict";
function updateCartQuantityListCartData()
{
    $('.update-cart-quantity-list-cart-data').off('click').on('click', function () {

        let minOrder = $(this).data('min-order');
        let cart = $(this).data('cart');
        let value = $(this).data('value');
        let action = $(this).data('action');
        updateCartQuantityList(minOrder, cart, value, action);
    });
    $('.update-cart-quantity-list-cart-data-input').off('change').on('change', function () {
        let minOrder = $(this).data('min-order');
        let cart = $(this).data('cart');
        let value = $(this).data('value');
        let action = $(this).data('action');
        updateCartQuantityList(minOrder, cart, value, action);
    });
}

updateCartQuantityListCartData();

function updateCartQuantityListMobileCartData()
{
    $('.update-cart-quantity-list-mobile-cart-data').off('click change').on('click change', function () {
        let minOrder = $(this).data('min-order');
        let cart = $(this).data('cart');
        let value = $(this).data('value');
        let action = $(this).data('action');
        updateCartQuantityListMobile(minOrder, cart, value, action);
    });
    $('.update-cart-quantity-list-mobile-cart-data-input').off('change').on('change', function () {
        let minOrder = $(this).data('min-order');
        let cart = $(this).data('cart');
        let value = $(this).data('value');
        let action = $(this).data('action');
        updateCartQuantityListMobile(minOrder, cart, value, action);
    });
}
updateCartQuantityListMobileCartData();

function updateCartQuantityList(minimum_order_qty, key, incr, e) {
    let ex_quantity = $("#cartQuantityWeb" + key);
    let quantity = parseInt(ex_quantity.val(), 10) + parseInt(incr, 10);
    updateCartCommon(minimum_order_qty, key, e, quantity, ex_quantity);
}

function updateCartQuantityListMobile(minimum_order_qty, key, incr, e) {
    let ex_quantity = $("#cartQuantityMobile" + key);
    let quantity = parseInt(ex_quantity.val(), 10) + parseInt(incr, 10);
    updateCartCommon(minimum_order_qty, key, e, quantity, ex_quantity);
}
function updateCartCommon(minimum_order_qty, key, e, quantity, ex_quantity) {
    let isDirectTopUp = parseInt(ex_quantity.data("is-direct-topup"), 10) === 1;
    let minQty = isDirectTopUp
        ? parseInt(ex_quantity.data("min"), 10) || minimum_order_qty
        : minimum_order_qty;
    let maxQty = isDirectTopUp
        ? parseInt(ex_quantity.data("max"), 10) ||
          parseInt(ex_quantity.data("current-stock"), 10) ||
          999999
        : parseInt(ex_quantity.data("current-stock"), 10) || 999999;

    if (isDirectTopUp) {
        quantity = Math.max(minQty, Math.min(maxQty, quantity));
    }

    if (!isDirectTopUp && ex_quantity.val() > ex_quantity.data('current-stock') && e == 'minus') {
        removeProductFromCartList(key)
        return false;
    }

    if (quantity < minQty && e !== 'delete') {
        if (e === 'plus' && quantity + 1 <= minQty) {
            quantity = quantity + 1;
            if (quantity < minQty) {
                $(".cartQuantity" + key).val(quantity);
                return false;
            }
        } else {
            toastr.error($('.minimum_order_quantity_msg').data('text') + ' ' + minQty);
            $(".cartQuantity" + key).val(minQty);
            return false;
        }
    }

    if (parseInt(ex_quantity.val(), 10) === parseInt(ex_quantity.data('min'), 10) && e === 'delete') {
        removeProductFromCartList(key)
    } else if (
        parseInt(ex_quantity.val(), 10) === parseInt(ex_quantity.data('min'), 10) &&
        e === 'minus'
    ) {
        removeProductFromCartList(key)
    } else {
        ex_quantity.val(quantity);

        const postData = {
            _token: $('meta[name="_token"]').attr('content'),
            key,
            quantity: isDirectTopUp ? 1 : quantity,
        };

        if (isDirectTopUp) {
            postData.direct_topup_quantity = quantity;
        }

        let updateQuantityBasicUrl = $('#update-quantity-basic-url').data('url');
        $.post(updateQuantityBasicUrl, postData, function (response) {
            if (response.status == 0) {
                toastr.error(response.message, {
                    CloseButton: true,
                    ProgressBar: true
                });
                $(".cartQuantity" + key).val(response['qty']);
            } else {
                if (parseInt(response['qty'], 10) === parseInt(ex_quantity.data('min'), 10)) {
                    ex_quantity.parent().find('.quantity__minus').html('<i class="bi bi-trash3-fill text-danger fs-10"></i>')
                } else {
                    ex_quantity.parent().find('.quantity__minus').html('<i class="bi bi-dash"></i>')
                }
                updateNavCart();
                $('#cart-summary').empty().html(response);
            }
            initTooltip();
            proceedToNextAction();
            setShippingIdFunction()
            updateCartQuantityListCartData();
            updateCartQuantityListMobileCartData();
            renderCouponCodeApply()
            multipleCheckBoxFunctionsInit()
        });
    }
}

function removeProductFromCartList(key) {
    let remove_from_cart_url = $('#remove_from_cart_url').data('url');
    $.post(remove_from_cart_url, {
            _token: $('meta[name="_token"]').attr('content'),
            key: key
        },
        function (response) {
            updateNavCart();
            toastr.info(response.message, {
                CloseButton: true,
                ProgressBar: true
            });
            let segment_array = window.location.pathname.split('/');
            let segment = segment_array[segment_array.length - 1];
            if (segment === 'checkout-payment' || segment === 'checkout-details') {
                location.reload();
            }
            $('#cart-summary').empty().html(response.data);
            initTooltip();
            proceedToNextAction();
            updateCartQuantityListCartData();
            setShippingIdFunction();
            updateCartQuantityListMobileCartData();
            renderCouponCodeApply()
            multipleCheckBoxFunctionsInit()
        }
    );
}

function setShippingIdFunction(){

    $('.set-shipping-onchange').on('change', function(){
        let Id = $(this).val();
        setShippingId(Id, 'all_cart_group');
    })
    $('.set-shipping-id').on('click', function(){
        let Id = $(this).data('id');
        let cartGroupId = $(this).data('cart-group');
        setShippingId(Id, cartGroupId);
    })
    function setShippingId(Id, cartGroupId) {
        $.get({
            url: $('#set-shipping-url').data('url'),
            dataType: 'json',
            data: {
                id: Id,
                cart_group_id: cartGroupId
            },
            beforeSend: function () {
                $('#loading').addClass('d-grid');
            },
            success: function () {
                location.reload();
            },
            complete: function () {
                $('#loading').removeClass('d-grid');
            },
        });
    }
}
setShippingIdFunction();

