"use strict";

function updateCartQuantityList(minimum_order_qty, key, incr, e) {
    let quantity_id = 'cart_quantity_web';
    updateCartCommon(minimum_order_qty, key, incr, e, quantity_id);
}

function updateCartQuantityListMobile(minimum_order_qty, key, incr, e) {
    let quantityId = 'cart_quantity_mobile';
    updateCartCommon(minimum_order_qty, key, incr, e, quantityId);
}

function updateCartCommon(minimum_order_qty, key, incr, e, quantity_id) {
    let exQuantity = $("#" + quantity_id + key);
    let isDirectTopUp = parseInt(exQuantity.data("is-direct-topup"), 10) === 1;
    let minQty = isDirectTopUp
        ? parseInt(exQuantity.data("min"), 10) || minimum_order_qty
        : minimum_order_qty;
    let maxQty = isDirectTopUp
        ? parseInt(exQuantity.data("max"), 10) ||
          parseInt(exQuantity.data("current-stock"), 10) ||
          999999
        : parseInt(exQuantity.data("current-stock"), 10) || 999999;

    let quantity = parseInt(exQuantity.val(), 10) + parseInt(incr, 10);

    if (isDirectTopUp) {
        quantity = Math.max(minQty, Math.min(maxQty, quantity));
    }

    if (!isDirectTopUp && exQuantity.val() > exQuantity.data('current-stock') && e == 'minus') {
        removeProductFromCartList(key)
        return false;
    }

    if (minQty > quantity && e != 'delete') {
        toastr.error($('#message-minimum-order-quantity-cannot-less-than').data('text') + minQty);
        $(".cartQuantity" + key).val(minQty);
        return false;
    }
    if (parseInt(exQuantity.val(), 10) == parseInt(exQuantity.data('min'), 10) && e == 'delete') {
        removeProductFromCartList(key)
    } else if (
        parseInt(exQuantity.val(), 10) == parseInt(exQuantity.data('min'), 10) &&
        e == 'minus'
    ) {
        removeProductFromCartList(key)
    } else {
        exQuantity.val(quantity);

        const postData = {
            _token: $('meta[name="_token"]').attr('content'),
            key,
            quantity: isDirectTopUp ? 1 : quantity,
        };

        if (isDirectTopUp) {
            postData.direct_topup_quantity = quantity;
        }

        $.post($('#route-cart-updateQuantity').data('url'), postData, function (response) {
            if (response.status == 0) {
                toastr.error(response.message, {
                    CloseButton: true,
                    ProgressBar: true
                });
                $(".cartQuantity" + key).val(response['qty']);
            } else {
                updateNavCart();
                $('#cart-summary').empty().html(response);
                $('[data-toggle="tooltip"]').tooltip()
                actionCheckoutFunctionInit()
                couponCode()
                setShippingIdFunctionCartDetails()
                cartListQuantityUpdateInit();
                quantityListener();
            }
        });
    }
}

function removeProductFromCartList(key) {
    $.post($('#route-cart-remove').data('url'), {
            _token: $('meta[name="_token"]').attr('content'),
            key: key
        },
        function (response) {
            updateNavCart();
            toastr.info($('#message-item-has-been-removed-from-cart').data('text'), {
                CloseButton: true,
                ProgressBar: true
            });
            let segmentArray = window.location.pathname.split('/');
            let segment = segmentArray[segmentArray.length - 1];
            if (segment === 'checkout-payment' || segment === 'checkout-details') {
                location.reload();
            }
            $('#cart-summary').empty().html(response.data)
            $('[data-toggle="tooltip"]').tooltip()
            actionCheckoutFunctionInit()
            couponCode()
            setShippingIdFunctionCartDetails();
            cartListQuantityUpdateInit();
            quantityListener();
        });
}

$('.qty_plus').on('click', function () {
    var $qty = $(this).parent().find('input');
    var currentVal = parseInt($qty.val());
    if (!isNaN(currentVal)) {
        $qty.val(currentVal + 1);
    }
    quantityListener();
});


$('.qty_minus').on('click', function () {
    var $qty = $(this).parent().find('input');
    var currentVal = parseInt($qty.val());
    if (!isNaN(currentVal) && currentVal > 1) {
        $qty.val(currentVal - 1);
    }
    quantityListener();
});


function quantityListener() {
    $('.qty_input').each(function () {
        var qty = $(this);
        var isDirectTopUp = parseInt(qty.data('is-direct-topup'), 10) === 1;
        var minimumOrderQuantity = isDirectTopUp
            ? (qty.data('min') ?? 1)
            : (qty.data('minimum-order') ?? 1);
        var currentStockQuantity = isDirectTopUp
            ? (qty.data('max') ?? qty.data('current-stock') ?? 1000)
            : (qty.data('current-stock') ?? 1000);
        if (qty.val() == 1 || qty.val() == minimumOrderQuantity ) {
            qty.siblings('.qty_minus').html('<i class="tio-delete text-danger"></i>')
        } else {
            qty.siblings('.qty_minus').html('<i class="fi fi-sr-minus fs-12 d-flex"></i>')
        }

        try {
            if (!isDirectTopUp && qty.val() > currentStockQuantity) {
                qty.siblings('.qty_minus').html('<i class="tio-delete text-danger"></i>')
            }
        }catch (e) {
        }

    });
}

quantityListener();

cartQuantityInitialize();


function setShippingId(id, cartGroupId) {
    $.get({
        url: $('#route-customer-set-shipping-method').data('url'),
        dataType: 'json',
        data: {
            id: id,
            cart_group_id: cartGroupId
        },
        beforeSend: function () {
            $('#loading').show();
        },
        success: function () {
            location.reload();
        },
        complete: function () {
            $('#loading').hide();
        },
    });
}

function setShippingIdFunctionCartDetails() {
    $('.setShippingIdFunctionCartDetails').on('click', function(){
        let id = $(this).data('id');
        let cartGroupId = $(this).data('cart-group');
        setShippingIdCartDetails(id, cartGroupId);
    })

    $('.set_shipping_onchange').on('change', function(){
        let id = $(this).val();
        setShippingIdCartDetails(id, 'all_cart_group');
    })

    function setShippingIdCartDetails(id, cart_group_id) {
        $.get({
            url: $('#route-set-shipping-id').data('url'),
            dataType: 'json',
            data: {
                id: id,
                cart_group_id: cart_group_id
            },
            beforeSend: function () {
                $('#loading').addClass('d-grid');
            },
            success: function (data) {
                location.reload();
            },
            complete: function () {
                $('#loading').removeClass('d-grid');
            },
        });
    }
}

setShippingIdFunctionCartDetails();
