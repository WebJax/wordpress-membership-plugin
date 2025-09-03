jQuery(document).ready(function($) {
    function initialize_product_search(element) {
        element.select2({
            ajax: {
                url: ajaxurl,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'search_products',
                        search: params.term
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.results
                    };
                },
                cache: true
            },
            minimumInputLength: 1
        });
    }

    function add_product_field(container, name, product) {
        var field = '<div class="product-field"><select name="' + name + '[]" class="product-search" style="width:300px;"></select> <button type="button" class="button remove-product">' + membership_settings.remove + '</button></div>';
        container.append(field);
        var select = container.find('.product-search:last');

        if( product ){
            var option = new Option(product.text, product.id, true, true);
            select.append(option).trigger('change');
        }

        initialize_product_search(select);
    }

    $('#add-automatic-product').on('click', function() {
        add_product_field($('#automatic-renewal-products'), 'membership_automatic_renewal_products');
    });

    $('#add-manual-product').on('click', function() {
        add_product_field($('#manual-renewal-products'), 'membership_manual_renewal_products');
    });

    $(document).on('click', '.remove-product', function() {
        $(this).parent().remove();
    });

    // Load existing products
    if (typeof membership_settings !== 'undefined') {
        if (membership_settings.automatic_products) {
            membership_settings.automatic_products.forEach(function(product) {
                add_product_field($('#automatic-renewal-products'), 'membership_automatic_renewal_products', product);
            });
        }
        if (membership_settings.manual_products) {
            membership_settings.manual_products.forEach(function(product) {
                add_product_field($('#manual-renewal-products'), 'membership_manual_renewal_products', product);
            });
        }
    }
});
