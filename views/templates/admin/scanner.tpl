<div class="panel">
    <div class="panel-heading">
        <i class="icon-barcode"></i> Music Scanner
    </div>
    
    <div class="panel-body">
        {if !$has_credentials}
            <div class="alert alert-warning">
                <strong>Let op:</strong> Configureer eerst je API credentials in de module instellingen.
                <a href="{$configure_url}" class="btn btn-primary btn-sm">
                    <i class="icon-cogs"></i> Naar Instellingen
                </a>
            </div>
        {else}
            <div class="row">
                <div class="col-lg-6">
                    <div class="form-group">
                        <label>EAN / Barcode</label>
                        <input type="text" id="ean_input" class="form-control" placeholder="Scan barcode of voer EAN in..." autofocus>
                        <p class="help-block">
                            <i class="icon-info-circle"></i> 
                            {if $auto_submit}
                                Auto-import is AAN - scan barcode voor automatische import
                            {else}
                                Druk Enter na scannen of klik "Zoeken"
                            {/if}
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label>API Bron</label>
                        <select id="api_source_select" class="form-control" style="width:250px;">
                            <option value="discogs" {if $api_source == 'discogs'}selected{/if}>Discogs </option>
                            <option value="bolcom" {if $api_source == 'bolcom'}selected{/if}>Bol.com (Met een prijsindicatie)</option>
                        </select>
                        <p class="help-block">
                            <i class="icon-info-circle"></i> Kies welke API je wilt gebruiken voor deze scan
                        </p>
                    </div>
                    
                    <button type="button" id="search_btn" class="btn btn-primary">
                        <i class="icon-search"></i> Zoeken
                    </button>
                    
                    <button type="button" id="clear_btn" class="btn btn-default">
                        <i class="icon-eraser"></i> Wissen
                    </button>
                </div>
                
                <div class="col-lg-6">
                    <div class="alert alert-info">
                        <strong>Standaard API:</strong> {if $api_source == 'discogs'}Discogs{else}Bol.com{/if}
                        <br>
                        <small>Wijzig standaard in module instellingen</small>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <!-- Loading indicator -->
            <div id="loading" style="display:none;" class="alert alert-info">
                <i class="icon-spinner icon-spin"></i> Zoeken naar product...
            </div>
            
            <!-- Duplicate notification -->
            <div id="duplicate_notification" style="display:none;" class="alert alert-warning">
                <i class="icon-info-circle"></i> <strong>Product bestaat al in voorraad!</strong>
                <p id="duplicate_message" style="margin:5px 0 0 0;"></p>
            </div>
            
            <!-- Preview area -->
            <div id="preview_area" style="display:none;">
                <h3><i class="icon-eye"></i> Product Preview</h3>
                
                <div class="row">
                    <div class="col-lg-3">
                        <img id="preview_image" src="" class="img-thumbnail" style="max-width:100%;">
                    </div>
                    
                    <div class="col-lg-9">
                        <table class="table">
                            <tr>
                                <th style="width:150px;">Titel:</th>
                                <td id="preview_title"></td>
                            </tr>
                            <tr>
                                <th>EAN:</th>
                                <td id="preview_ean"></td>
                            </tr>
                            <tr>
                                <th>Beschrijving:</th>
                                <td id="preview_description"></td>
                            </tr>
                            <tr>
                                <th>Prijs:</th>
                                <td>
                                    <span id="preview_price"></span>
                                    <span id="preview_price_original" style="display:none; text-decoration:line-through; color:#999;"></span>
                                    <input type="number" id="edit_price" class="form-control" style="width:150px; display:inline-block;" step="0.01">
                                    <button type="button" id="edit_price_btn" class="btn btn-sm btn-default">
                                        <i class="icon-edit"></i> Aanpassen
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <th>Voorraad:</th>
                                <td>
                                    <span id="current_stock" style="font-weight:bold; font-size:16px;">0</span>
                                    <span id="stock_add_section" style="display:none; margin-left:20px;">
                                        + <input type="number" id="stock_to_add" class="form-control" style="width:80px; display:inline-block;" value="1" min="1">
                                    </span>
                                    <span id="stock_new_section" style="display:none; margin-left:10px;">
                                        <input type="number" id="edit_stock" class="form-control" style="width:100px; display:inline-block;" value="1" min="0">
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Categorie:</th>
                                <td>
                                    <select id="edit_category" class="form-control" style="width:200px; display:inline-block;">
                                        <option value="2">Home</option>
                                        <option value="3">CD's</option>
                                        <option value="4">Vinyl</option>
                                        <option value="5">DVD's</option>
                                    </select>
                                    <span id="category_detected" class="label label-success" style="display:none; margin-left:10px;">
                                        <i class="icon-check"></i> Automatisch gedetecteerd
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Formaat:</th>
                                <td id="preview_format"></td>
                            </tr>
                        </table>
                        
                        <form method="post" id="import_form">
                            <input type="hidden" name="product_data" id="product_data">
                            <button type="submit" name="submitImportProduct" class="btn btn-success btn-lg">
                                <i class="icon-check"></i> Product Importeren
                            </button>
                            <button type="button" id="cancel_btn" class="btn btn-default btn-lg">
                                <i class="icon-times"></i> Annuleren
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</div>

<script type="text/javascript">
$(document).ready(function() {
    var currentProduct = null;
    var autoSubmit = {if $auto_submit}true{else}false{/if};
    
    // Search button click
    $('#search_btn').click(function() {
        searchProduct();
    });
    
    // Enter key
    $('#ean_input').keypress(function(e) {
        if (e.which === 13) {
            e.preventDefault();
            if (autoSubmit) {
                // Auto-submit: search and import immediately
                searchAndImport();
            } else {
                // Manual: just search for preview
                searchProduct();
            }
        }
    });
    
    // Auto-submit after barcode scan (if enabled)
    if (autoSubmit) {
        var timeout;
        $('#ean_input').on('input', function() {
            clearTimeout(timeout);
            var value = $(this).val();
            if (value.length >= 8) {
                timeout = setTimeout(function() {
                    searchAndImport();
                }, 100);
            }
        });
    }
    
    // Clear button
    $('#clear_btn').click(function() {
        $('#ean_input').val('').focus();
        $('#preview_area').hide();
        $('#duplicate_notification').hide();
        currentProduct = null;
    });
    
    // Cancel button
    $('#cancel_btn').click(function() {
        $('#preview_area').hide();
        $('#ean_input').val('').focus();
        currentProduct = null;
    });
    
    // Edit price button
    $('#edit_price_btn').click(function() {
        var newPrice = parseFloat($('#edit_price').val());
        if (!isNaN(newPrice) && newPrice >= 0) {
            currentProduct.price = newPrice;
            $('#preview_price').text('€' + newPrice.toFixed(2));
        }
    });
    
    // Edit stock
    $('#edit_stock').change(function() {
        currentProduct.stock = parseInt($(this).val());
    });
    
    // Edit category
    $('#edit_category').change(function() {
        currentProduct.category = parseInt($(this).val());
    });
    
    // Form submit
    $('#import_form').submit(function() {
        // Update stock based on whether it's a duplicate or new product
        if (currentProduct.isDuplicate) {
            currentProduct.stock = parseInt($('#stock_to_add').val()) || 1;
        } else {
            currentProduct.stock = parseInt($('#edit_stock').val()) || 1;
        }
        
        // Update category
        currentProduct.category = parseInt($('#edit_category').val());
        
        $('#product_data').val(JSON.stringify(currentProduct));
    });
    
    // Search product
    function searchProduct() {
        var ean = $('#ean_input').val().trim();
        if (!ean) {
            alert('Voer een EAN code in');
            return;
        }
        
        var apiSource = $('#api_source_select').val();
        
        $('#loading').show();
        $('#preview_area').hide();
        
        $.ajax({
            url: '{$current_index}&token={$token}',
            method: 'POST',
            data: { 
                ajax: 1,
                action: 'preview',
                ean: ean,
                api_source: apiSource
            },
            dataType: 'json',
            success: function(response) {
                $('#loading').hide();
                
                if (response.success) {
                    if (response.duplicate) {
                        // Show duplicate notification
                        $('#duplicate_message').text(response.message);
                        $('#duplicate_notification').show();
                        // Show duplicate product with update stock option
                        showPreview(response.product, true);
                    } else {
                        $('#duplicate_notification').hide();
                        showPreview(response.product, false);
                    }
                } else {
                    alert(response.message);
                    $('#ean_input').val('').focus();
                }
            },
            error: function() {
                $('#loading').hide();
                alert('Er is een fout opgetreden bij het zoeken');
                $('#ean_input').val('').focus();
            }
        });
    }
    
    // Search and import immediately (auto-submit mode)
    function searchAndImport() {
        var ean = $('#ean_input').val().trim();
        if (!ean) return;
        
        var apiSource = $('#api_source_select').val();
        
        $('#loading').show();
        
        $.ajax({
            url: '{$current_index}&token={$token}',
            method: 'POST',
            data: { 
                ajax: 1,
                action: 'preview',
                ean: ean,
                api_source: apiSource
            },
            dataType: 'json',
            success: function(response) {
                $('#loading').hide();
                
                if (response.success) {
                    if (response.duplicate) {
                        // Show notification for duplicate
                        $('#duplicate_message').html('<strong>' + response.product.title + '</strong> - Voorraad verhoogd (+1)');
                        $('#duplicate_notification').show();
                        
                        // Auto-update stock
                        currentProduct = response.product;
                        currentProduct.stock = 1; // Add 1 to stock
                        $('#product_data').val(JSON.stringify(currentProduct));
                        $('#import_form').submit();
                        
                        // Hide notification after 3 seconds
                        setTimeout(function() {
                            $('#duplicate_notification').fadeOut();
                            $('#ean_input').val('').focus();
                        }, 3000);
                    } else {
                        // Show success notification for new product
                        $('#duplicate_notification').removeClass('alert-warning').addClass('alert-success');
                        $('#duplicate_message').html('<strong>' + response.product.title + '</strong> - Toegevoegd');
                        $('#duplicate_notification').show();
                        
                        // Auto-import without preview
                        currentProduct = response.product;
                        $('#product_data').val(JSON.stringify(currentProduct));
                        $('#import_form').submit();
                        
                        // Hide notification after 3 seconds
                        setTimeout(function() {
                            $('#duplicate_notification').fadeOut();
                            $('#ean_input').val('').focus();
                        }, 3000);
                    }
                } else {
                    // Show error
                    $('#duplicate_notification').removeClass('alert-success').addClass('alert-danger');
                    $('#duplicate_message').text(response.message);
                    $('#duplicate_notification').show();
                    
                    setTimeout(function() {
                        $('#duplicate_notification').fadeOut();
                        $('#ean_input').val('').focus();
                    }, 3000);
                }
            },
            error: function() {
                $('#loading').hide();
                $('#duplicate_notification').removeClass('alert-success').addClass('alert-danger');
                $('#duplicate_message').text('Er is een fout opgetreden bij het zoeken');
                $('#duplicate_notification').show();
                
                setTimeout(function() {
                    $('#duplicate_notification').fadeOut();
                    $('#ean_input').val('').focus();
                }, 3000);
            }
        });
    }
    
    // Show preview
    function showPreview(product, isDuplicate) {
        currentProduct = product;
        currentProduct.isDuplicate = isDuplicate || false;
        
        // Convert price to number if it's a string
        var price = parseFloat(product.price);
        var stock = parseInt(product.stock);
        
        $('#preview_title').text(product.title);
        $('#preview_ean').text(product.ean);
        $('#preview_description').html(product.description);
        $('#preview_price').text('€' + price.toFixed(2));
        $('#edit_price').val(price);
        
        // Show stock differently for duplicates vs new products
        if (isDuplicate) {
            $('#current_stock').text(stock);
            $('#stock_add_section').show();
            $('#stock_new_section').hide();
            $('#stock_to_add').val(1);
        } else {
            $('#current_stock').text('Nieuw product');
            $('#stock_add_section').hide();
            $('#stock_new_section').show();
            $('#edit_stock').val(1);
        }
        
        // Set category
        if (product.category) {
            $('#edit_category').val(product.category);
            $('#category_detected').show();
        } else {
            $('#edit_category').val(2); // Default: Home
            $('#category_detected').hide();
        }
        
        // Show format
        if (product.format) {
            $('#preview_format').text(product.format);
        } else {
            $('#preview_format').text('-');
        }
        
        if (product.price_original) {
            var priceOriginal = parseFloat(product.price_original);
            $('#preview_price_original').text('€' + priceOriginal.toFixed(2)).show();
        } else {
            $('#preview_price_original').hide();
        }
        
        if (product.image_url) {
            $('#preview_image').attr('src', product.image_url).show();
        } else {
            $('#preview_image').hide();
        }
        
        // Change button text for duplicates
        if (isDuplicate) {
            var addAmount = $('#stock_to_add').val() || 1;
            $('#import_btn').html('<i class="icon-plus"></i> Voorraad Verhogen (+' + addAmount + ')');
        } else {
            $('#import_btn').html('<i class="icon-download"></i> Product Importeren');
        }
        
        $('#preview_area').show();
    }
    
    // Update button text when stock_to_add changes
    $(document).on('input', '#stock_to_add', function() {
        var addAmount = $(this).val() || 1;
        $('#import_btn').html('<i class="icon-plus"></i> Voorraad Verhogen (+' + addAmount + ')');
    });
});
</script>

<div class="panel" style="margin-top: 20px;">
    <div class="panel-body text-center" style="padding: 15px;">
        <p style="margin: 0; color: #6c868e;">
            <i class="icon-code"></i> 
            Ontwikkeld door <strong>Daan Stam</strong>
            <br>
            <a href="https://github.com/Daan026" target="_blank" style="color: #25b9d7; text-decoration: none;">
                <i class="icon-github"></i> GitHub: Daan026
            </a>
        </p>
    </div>
</div>
