<?php
/**
 * Music Scanner Admin Controller
 * Dedicated page for scanning and importing music products
 * 
 * @author Daniel Stam
 * @version 2.0.0
 */

class AdminMusicScannerController extends ModuleAdminController
{
    /**
     * Log een foutmelding naar het logbestand
     */
    protected function logError($message, $context = [])
    {
        $logMessage = '[' . date('Y-m-d H:i:s') . '] [AdminMusicScannerController] ' . $message;
        if (!empty($context)) {
            $logMessage .= ' | Context: ' . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        $logMessage .= "\n";
        
        $logFile = _PS_ROOT_DIR_ . '/var/logs/musicscanner.log';
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    public function __construct()
    {
        $this->bootstrap = true;
        
        try {
            $this->logError('__construct aangeroepen');
            parent::__construct();
            $this->logError('parent::__construct succesvol');
            
            // Zet meta_title NA parent::__construct()
            $this->meta_title = 'Music Scanner';
            $this->logError('__construct voltooid');
        } catch (Exception $e) {
            $this->logError('Fout in __construct: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    public function initContent()
    {
        try {
            $this->logError('initContent aangeroepen');
            parent::initContent();
            $this->logError('parent::initContent succesvol');
            
            $this->content = $this->renderView();
            $this->logError('renderView succesvol');
            
            $this->context->smarty->assign('content', $this->content);
            $this->logError('initContent voltooid');
        } catch (Exception $e) {
            $this->logError('Fout in initContent: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function renderView()
    {
        try {
            $this->logError('renderView gestart', [
                'ajax' => Tools::getValue('ajax'),
                'action' => Tools::getValue('action'),
                'isAjax' => Tools::isSubmit('ajax') ? 'ja' : 'nee'
            ]);
            
            // Handle AJAX preview request FIRST
            if (Tools::getValue('ajax') == 1 && Tools::getValue('action') === 'preview') {
                $this->logError('AJAX preview request ontvangen - calling ajaxProcessPreview');
                $this->ajaxProcessPreview();
                exit; // Stop execution after AJAX response
            }
            
            // Get module instance
            $module = Module::getInstanceByName('musiceanscanner');
            $this->logError('Module instance opgehaald', ['module' => $module ? 'gevonden' : 'niet gevonden']);
            
            if (!$module) {
                $this->logError('FOUT: Module niet gevonden!');
                return '<div class="alert alert-danger">Module niet gevonden. Controleer of de module correct is geïnstalleerd.</div>';
            }
            
            // Check if API is configured
            $apiSource = Configuration::get('MUSIC_API_SOURCE');
            $hasCredentials = false;
            
            if ($apiSource === 'discogs' && Configuration::get('DISCOGS_API_TOKEN')) {
                $hasCredentials = true;
            } elseif ($apiSource === 'bolcom' && Configuration::get('BOLCOM_API_KEY') && Configuration::get('BOLCOM_API_SECRET')) {
                $hasCredentials = true;
            }
            
            $this->logError('API configuratie gecheckt', [
                'apiSource' => $apiSource,
                'hasCredentials' => $hasCredentials ? 'ja' : 'nee'
            ]);
            
            // Handle import confirmation
            if (Tools::isSubmit('submitImportProduct')) {
                $this->logError('Import product formulier verzonden');
                $productData = Tools::getValue('product_data');
                if ($productData) {
                    $productData = json_decode($productData, true);
                    $result = $this->importProduct($productData);
                    
                    if ($result['success']) {
                        $this->confirmations[] = $result['message'];
                    } else {
                        $this->errors[] = $result['message'];
                    }
                }
            }
            
            // Build configure URL
            $adminController = Context::getContext()->link->getAdminLink('AdminModules', true);
            $configureUrl = $adminController . '&configure=musiceanscanner&tab_module=administration&module_name=musiceanscanner';
            
            // Assign template variables
            $this->context->smarty->assign([
                'has_credentials' => $hasCredentials,
                'api_source' => $apiSource,
                'auto_submit' => Configuration::get('BOLCOM_AUTO_SUBMIT'),
                'module_dir' => $module->getPathUri(),
                'current_index' => self::$currentIndex,
                'token' => $this->token,
                'configure_url' => $configureUrl
            ]);
            
            $this->logError('Template variabelen toegewezen');
            
            $templatePath = $module->getLocalPath() . 'views/templates/admin/scanner.tpl';
            $this->logError('Template pad', ['path' => $templatePath, 'exists' => file_exists($templatePath) ? 'ja' : 'nee']);
            
            $result = $this->context->smarty->fetch($templatePath);
            $this->logError('Template gefetched', ['length' => strlen($result)]);
            
            return $result;
            
        } catch (Exception $e) {
            $this->logError('FOUT in renderView: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return '<div class="alert alert-danger">Er is een fout opgetreden: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    
    /**
     * AJAX: Preview product before import
     */
    public function ajaxProcessPreview()
    {
        $ean = Tools::getValue('ean');
        
        $this->logError('ajaxProcessPreview aangeroepen', ['ean' => $ean]);
        
        if (!$ean) {
            $this->logError('Geen EAN code opgegeven');
            die(json_encode(['success' => false, 'message' => 'Geen EAN code opgegeven']));
        }
        
        $module = Module::getInstanceByName('musiceanscanner');
        
        // Check for duplicate
        $existingProduct = $this->checkDuplicate($ean);
        if ($existingProduct) {
            $this->logError('Product bestaat al', ['ean' => $ean, 'product_id' => $existingProduct->id]);
            
            // Get current stock
            $currentStock = StockAvailable::getQuantityAvailableByProduct($existingProduct->id);
            
            // Get product image
            $imageUrl = '';
            $cover = Image::getCover($existingProduct->id);
            if ($cover) {
                $imageUrl = $this->context->link->getImageLink(
                    $existingProduct->link_rewrite[(int)Configuration::get('PS_LANG_DEFAULT')],
                    $existingProduct->id . '-' . $cover['id_image'],
                    'large_default'
                );
            }
            
            // Return existing product info with option to update stock
            die(json_encode([
                'success' => true,
                'duplicate' => true,
                'product' => [
                    'id' => $existingProduct->id,
                    'title' => $existingProduct->name[(int)Configuration::get('PS_LANG_DEFAULT')],
                    'ean' => $ean,
                    'price' => $existingProduct->price,
                    'stock' => $currentStock,
                    'description' => strip_tags($existingProduct->description[(int)Configuration::get('PS_LANG_DEFAULT')]),
                    'image_url' => $imageUrl,
                    'category' => $existingProduct->id_category_default
                ],
                'message' => 'Product bestaat al. Voorraad: ' . $currentStock . '. Wil je de voorraad verhogen?'
            ]));
        }
        
        // Get API source from request or use default
        $apiSource = Tools::getValue('api_source');
        if (!$apiSource) {
            $apiSource = Configuration::get('MUSIC_API_SOURCE');
        }
        
        $this->logError('API bron geselecteerd', ['apiSource' => $apiSource]);
        
        // Search product
        if ($apiSource === 'discogs') {
            $this->logError('Zoeken op Discogs...', ['ean' => $ean]);
            $productData = $this->searchDiscogs($ean);
            $this->logError('Discogs zoekresultaat', ['found' => $productData ? 'ja' : 'nee', 'data' => $productData]);
        } else {
            $this->logError('Zoeken op Bol.com...', ['ean' => $ean]);
            $productData = $this->searchBolcom($ean);
            $this->logError('Bol.com zoekresultaat', ['found' => $productData ? 'ja' : 'nee']);
        }
        
        if (!$productData) {
            $this->logError('Product niet gevonden', ['ean' => $ean, 'apiSource' => $apiSource]);
            die(json_encode([
                'success' => false,
                'message' => 'Product niet gevonden met EAN: ' . $ean
            ]));
        }
        
        // Apply price markup
        $markup = (float)Configuration::get('BOLCOM_PRICE_MARKUP');
        if ($markup > 0 && $productData['price'] > 0) {
            $productData['price_original'] = $productData['price'];
            $productData['price'] = round($productData['price'] * (1 + ($markup / 100)), 2);
        }
        
        $productData['stock'] = (int)Configuration::get('BOLCOM_DEFAULT_STOCK');
        
        die(json_encode([
            'success' => true,
            'product' => $productData
        ]));
    }
    
    /**
     * Import product after confirmation
     */
    protected function importProduct($productData)
    {
        try {
            // Check if this is a duplicate (update stock)
            if (isset($productData['id']) && $productData['id'] > 0) {
                $product = new Product($productData['id']);
                if (Validate::isLoadedObject($product)) {
                    // Update stock
                    $currentStock = StockAvailable::getQuantityAvailableByProduct($product->id);
                    $newStock = $currentStock + (int)$productData['stock'];
                    StockAvailable::setQuantity($product->id, 0, $newStock);
                    
                    return [
                        'success' => true,
                        'message' => 'Voorraad verhoogd van ' . $currentStock . ' naar ' . $newStock . ' voor: ' . $product->name[(int)Configuration::get('PS_LANG_DEFAULT')] . ' (ID: ' . $product->id . ')'
                    ];
                }
            }
            
            $product = new Product();
            $product->name = [
                (int)Configuration::get('PS_LANG_DEFAULT') => $productData['title']
            ];
            $product->link_rewrite = [
                (int)Configuration::get('PS_LANG_DEFAULT') => Tools::str2url($productData['title'])
            ];
            $product->description = [
                (int)Configuration::get('PS_LANG_DEFAULT') => $productData['description']
            ];
            $product->description_short = [
                (int)Configuration::get('PS_LANG_DEFAULT') => Tools::substr($productData['description'], 0, 400)
            ];
            
            $product->ean13 = $productData['ean'];
            $product->price = $productData['price'];
            $product->id_category_default = isset($productData['category']) ? (int)$productData['category'] : 2;
            $product->active = 1;
            $product->id_category = [isset($productData['category']) ? (int)$productData['category'] : 2];

            if ($product->add()) {
                if (!empty($productData['image_url'])) {
                    $this->addProductImage($product->id, $productData['image_url']);
                }

                StockAvailable::setQuantity($product->id, 0, $productData['stock']);

                return [
                    'success' => true,
                    'message' => 'Product succesvol geïmporteerd: ' . $productData['title'] . ' (ID: ' . $product->id . ')'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Fout bij aanmaken product in database'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check for duplicate product
     */
    protected function checkDuplicate($ean)
    {
        $productId = Product::getIdByEan13($ean);
        if ($productId) {
            return new Product($productId);
        }
        return false;
    }
    
    /**
     * Search on Discogs
     */
    protected function searchDiscogs($ean)
    {
        $apiToken = Configuration::get('DISCOGS_API_TOKEN');
        $url = 'https://api.discogs.com/database/search?barcode=' . urlencode($ean) . '&type=release&token=' . urlencode($apiToken);
        
        $this->logError('Discogs API call', [
            'url' => 'https://api.discogs.com/database/search?barcode=' . urlencode($ean) . '&type=release&token=***',
            'token' => !empty($apiToken) ? 'aanwezig (***' . substr($apiToken, -4) . ')' : 'LEEG!'
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Project38Music/1.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $this->logError('Discogs API response', [
            'httpCode' => $httpCode,
            'curlError' => $curlError ?: 'geen',
            'responseLength' => strlen($response),
            'response' => substr($response, 0, 500)
        ]);

        if ($httpCode !== 200 || !$response) {
            $this->logError('Discogs API fout', [
                'httpCode' => $httpCode,
                'hasResponse' => !empty($response)
            ]);
            return null;
        }

        $data = json_decode($response, true);
        
        $this->logError('Discogs API data', [
            'hasResults' => !empty($data['results']),
            'resultCount' => isset($data['results']) ? count($data['results']) : 0,
            'data' => $data
        ]);
        
        if (empty($data['results'])) {
            return null;
        }

        $release = $data['results'][0];
        
        // Get full release details if we have a release ID
        $releaseDetails = null;
        if (!empty($release['id'])) {
            $releaseDetails = $this->getDiscogsReleaseDetails($release['id'], $apiToken);
        }
        
        // Build description with professional HTML formatting
        $description = '<div class="product-description">';
        
        // Product details section
        $description .= '<div class="product-details">';
        $description .= '<h3>Productinformatie</h3>';
        $description .= '<ul>';
        
        if (!empty($release['year'])) {
            $description .= '<li><strong>Release jaar:</strong> ' . $release['year'] . '</li>';
        }
        if (!empty($release['label'])) {
            $description .= '<li><strong>Label:</strong> ' . implode(', ', $release['label']) . '</li>';
        }
        if (!empty($release['genre'])) {
            $description .= '<li><strong>Genre:</strong> ' . implode(', ', $release['genre']) . '</li>';
        }
        if (!empty($release['style'])) {
            $description .= '<li><strong>Stijl:</strong> ' . implode(', ', $release['style']) . '</li>';
        }
        if (!empty($release['format'])) {
            $description .= '<li><strong>Formaat:</strong> ' . implode(', ', $release['format']) . '</li>';
        }
        
        $description .= '</ul>';
        $description .= '</div>';
        
        // Add tracklist if available
        if ($releaseDetails && !empty($releaseDetails['tracklist'])) {
            $description .= '<div class="product-tracklist">';
            $description .= '<h3>Tracklist</h3>';
            $description .= '<ol>';
            foreach ($releaseDetails['tracklist'] as $track) {
                $description .= '<li>';
                $description .= '<strong>' . htmlspecialchars($track['title']) . '</strong>';
                if (!empty($track['duration'])) {
                    $description .= ' <span class="track-duration">(' . $track['duration'] . ')</span>';
                }
                $description .= '</li>';
            }
            $description .= '</ol>';
            $description .= '</div>';
        }
        
        $description .= '</div>';
        
        // Add CSS styling
        $description .= '<style>
            .product-description { font-family: Arial, sans-serif; line-height: 1.6; }
            .product-description h3 { color: #333; font-size: 18px; margin-top: 20px; margin-bottom: 10px; border-bottom: 2px solid #e0e0e0; padding-bottom: 5px; }
            .product-details ul { list-style: none; padding: 0; }
            .product-details li { padding: 5px 0; border-bottom: 1px solid #f0f0f0; }
            .product-details li:last-child { border-bottom: none; }
            .product-tracklist ol { padding-left: 20px; }
            .product-tracklist li { padding: 5px 0; }
            .track-duration { color: #666; font-size: 0.9em; }
        </style>';
        
        // Detect category based on format
        $category = $this->detectCategory($release['format'] ?? []);
        
        return [
            'title' => $release['title'] ?? 'Onbekend Product',
            'description' => $description ?: 'Geen beschrijving beschikbaar',
            'price' => 0,
            'image_url' => $release['cover_image'] ?? $release['thumb'] ?? '',
            'ean' => $ean,
            'category' => $category,
            'format' => implode(', ', $release['format'] ?? [])
        ];
    }
    
    /**
     * Get full release details from Discogs
     */
    protected function getDiscogsReleaseDetails($releaseId, $apiToken)
    {
        $url = 'https://api.discogs.com/releases/' . $releaseId;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Project38Music/1.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Discogs token=' . $apiToken,
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        return json_decode($response, true);
    }
    
    /**
     * Detect category based on format
     */
    protected function detectCategory($formats)
    {
        if (empty($formats)) {
            return 2; // Default: Home
        }
        
        $formatString = strtolower(implode(' ', $formats));
        
        // Check for Vinyl
        if (strpos($formatString, 'vinyl') !== false || 
            strpos($formatString, 'lp') !== false || 
            strpos($formatString, '12"') !== false ||
            strpos($formatString, '7"') !== false) {
            return $this->getCategoryIdByName('Vinyl');
        }
        
        // Check for CD
        if (strpos($formatString, 'cd') !== false) {
            return $this->getCategoryIdByName('CD');
        }
        
        // Check for DVD/Blu-ray
        if (strpos($formatString, 'dvd') !== false || 
            strpos($formatString, 'blu-ray') !== false ||
            strpos($formatString, 'bluray') !== false) {
            return $this->getCategoryIdByName('DVD');
        }
        
        // Default
        return 2; // Home
    }
    
    /**
     * Get category ID by name (or create if not exists)
     */
    protected function getCategoryIdByName($name)
    {
        $categories = Category::searchByName((int)Configuration::get('PS_LANG_DEFAULT'), $name);
        
        if (!empty($categories)) {
            return (int)$categories[0]['id_category'];
        }
        
        // Category doesn't exist, return Home
        return 2;
    }
    
    /**
     * Search on Bol.com
     */
    protected function searchBolcom($ean)
    {
        $apiKey = Configuration::get('BOLCOM_API_KEY');
        $apiSecret = Configuration::get('BOLCOM_API_SECRET');
        
        $url = 'https://api.bol.com/catalog/v4/search?q=' . urlencode($ean) . '&format=json';
        $auth = base64_encode($apiKey . ':' . $apiSecret);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Project38Music/1.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $auth,
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        $data = json_decode($response, true);
        
        if (empty($data['products'])) {
            return null;
        }

        $product = $data['products'][0];
        
        // Build description with professional HTML formatting
        $description = '<div class="product-description">';
        
        // Product summary
        if (!empty($product['summary'])) {
            $description .= '<div class="product-summary">';
            $description .= '<p>' . htmlspecialchars($product['summary']) . '</p>';
            $description .= '</div>';
        }
        
        // Product details section
        $description .= '<div class="product-details">';
        $description .= '<h3>Productinformatie</h3>';
        $description .= '<ul>';
        
        if (!empty($product['publisherName'])) {
            $description .= '<li><strong>Uitgever:</strong> ' . htmlspecialchars($product['publisherName']) . '</li>';
        }
        if (!empty($product['releaseDate'])) {
            $description .= '<li><strong>Release datum:</strong> ' . htmlspecialchars($product['releaseDate']) . '</li>';
        }
        if (!empty($product['specsTag'])) {
            $description .= '<li><strong>Specificaties:</strong> ' . htmlspecialchars($product['specsTag']) . '</li>';
        }
        
        $description .= '</ul>';
        $description .= '</div>';
        $description .= '</div>';
        
        // Add CSS styling
        $description .= '<style>
            .product-description { font-family: Arial, sans-serif; line-height: 1.6; }
            .product-summary { margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #007bff; }
            .product-summary p { margin: 0; }
            .product-description h3 { color: #333; font-size: 18px; margin-top: 20px; margin-bottom: 10px; border-bottom: 2px solid #e0e0e0; padding-bottom: 5px; }
            .product-details ul { list-style: none; padding: 0; }
            .product-details li { padding: 5px 0; border-bottom: 1px solid #f0f0f0; }
            .product-details li:last-child { border-bottom: none; }
        </style>';
        
        // Detect category from product type
        $category = 2; // Default: Home
        if (!empty($product['productType'])) {
            $productType = strtolower($product['productType']);
            if (strpos($productType, 'cd') !== false) {
                $category = $this->getCategoryIdByName('CD');
            } elseif (strpos($productType, 'vinyl') !== false || strpos($productType, 'lp') !== false) {
                $category = $this->getCategoryIdByName('Vinyl');
            } elseif (strpos($productType, 'dvd') !== false || strpos($productType, 'blu-ray') !== false) {
                $category = $this->getCategoryIdByName('DVD');
            }
        }
        
        return [
            'title' => $product['title'] ?? 'Onbekend Product',
            'description' => $description ?: 'Geen beschrijving beschikbaar',
            'price' => isset($product['offerData']['offers'][0]['price']) 
                ? (float)$product['offerData']['offers'][0]['price'] 
                : 0,
            'image_url' => $product['images'][0]['url'] ?? '',
            'ean' => $ean,
            'category' => $category,
            'format' => $product['productType'] ?? ''
        ];
    }
    
    /**
     * Add image to product
     */
    protected function addProductImage($productId, $imageUrl)
    {
        try {
            $image = new Image();
            $image->id_product = $productId;
            $image->position = Image::getHighestPosition($productId) + 1;
            $image->cover = true;

            if ($image->add()) {
                $path = _PS_IMG_DIR_ . 'p/' . $image->getImgFolder();
                
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }

                $imageContent = Tools::file_get_contents($imageUrl);
                if ($imageContent) {
                    file_put_contents($path . $image->id . '.jpg', $imageContent);
                    
                    $imagesTypes = ImageType::getImagesTypes('products');
                    foreach ($imagesTypes as $imageType) {
                        ImageManager::resize(
                            $path . $image->id . '.jpg',
                            $path . $image->id . '-' . stripslashes($imageType['name']) . '.jpg',
                            $imageType['width'],
                            $imageType['height']
                        );
                    }
                }
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Music Scanner: Failed to add image - ' . $e->getMessage(), 2);
        }
    }
}
