<?php
/**
 * Music EAN Product Importer
 * Import muziek producten via Discogs of Bol.com API
 * 
 * @author Daniel Stam
 * @version 2.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MusicEanScanner extends Module
{
    /**
     * Log een foutmelding naar het logbestand
     * 
     * @param string $message Het foutbericht
     * @param array $context Optionele extra contextinformatie
     * @return bool Of het loggen is gelukt
     */
    public function logError($message, $context = [])
    {
        $logMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message;
        if (!empty($context)) {
            $logMessage .= ' | Context: ' . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        $logMessage .= "\n";
        
        $logFile = _PS_ROOT_DIR_ . '/var/logs/musicscanner.log';
        return @file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    public function __construct()
    {
        $this->name = 'musiceanscanner';
        $this->tab = 'administration';
        $this->version = '2.0.1';
        $this->author = 'Daniel Stam';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => '9.0.0'
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Music EAN Scanner');
        $this->description = $this->l('Scan en importeer CD\'s, Vinyl, DVD\'s via Discogs of Bol.com API');
        $this->confirmUninstall = $this->l('Weet je zeker dat je deze module wilt verwijderen?');
        
        // Zorg ervoor dat de logs map bestaat en schrijfbaar is
        $logsDir = _PS_ROOT_DIR_ . '/var/logs';
        if (!file_exists($logsDir)) {
            @mkdir($logsDir, 0755, true);
        }
        if (file_exists($logsDir) && !is_writable($logsDir)) {
            @chmod($logsDir, 0755);
        }
    }

    public function install()
    {
        // Check PHP requirements
        if (version_compare(PHP_VERSION, '7.2.0', '<')) {
            $this->_errors[] = $this->l('Deze module vereist PHP 7.2 of hoger');
            return false;
        }
        
        // Check cURL extension
        if (!function_exists('curl_init')) {
            $this->_errors[] = $this->l('Deze module vereist de PHP cURL extensie');
            return false;
        }
        
        // Set default configuration
        Configuration::updateValue('MUSIC_API_SOURCE', 'discogs');
        Configuration::updateValue('DISCOGS_API_TOKEN', '');
        Configuration::updateValue('DISCOGS_CONSUMER_KEY', '');
        Configuration::updateValue('DISCOGS_CONSUMER_SECRET', '');
        Configuration::updateValue('BOLCOM_API_KEY', '');
        Configuration::updateValue('BOLCOM_API_SECRET', '');
        Configuration::updateValue('BOLCOM_PRICE_MARKUP', '0');
        Configuration::updateValue('BOLCOM_PRICE_MARKUP_TYPE', 'percentage');
        Configuration::updateValue('BOLCOM_DEFAULT_STOCK', '1');
        Configuration::updateValue('BOLCOM_AUTO_SUBMIT', '0');
        
        // Create music categories if they don't exist
        try {
            $this->createMusicCategories();
        } catch (Exception $e) {
            $this->logError('Fout bij aanmaken categorieën: ' . $e->getMessage());
        }
        
        // Remove any duplicate tabs first
        $this->removeDuplicateTabs();
        
        // Create admin tab only if it doesn't exist
        $tabId = (int)Tab::getIdFromClassName('AdminMusicScanner');
        if (!$tabId) {
            $tab = new Tab();
            $tab->active = 1;
            $tab->class_name = 'AdminMusicScanner';
            $tab->name = [];
            foreach (Language::getLanguages(true) as $lang) {
                $tab->name[$lang['id_lang']] = 'Music Scanner';
            }
            $tab->id_parent = (int)Tab::getIdFromClassName('AdminCatalog');
            $tab->module = $this->name;
            
            if (!$tab->add()) {
                $this->logError('Fout bij aanmaken admin tab');
            }
        }
        
        $installed = parent::install() && $this->registerHook('displayBackOfficeHeader');
        
        if ($installed) {
            $this->logError('Module succesvol geïnstalleerd', ['version' => $this->version]);
        }
        
        return $installed;
    }
    
    /**
     * Create music categories (CD's, Vinyl, DVD's) if they don't exist
     */
    private function createMusicCategories()
    {
        $defaultLang = (int)Configuration::get('PS_LANG_DEFAULT');
        $homeCategory = 2; // Home category ID
        
        $categories = [
            ['name' => "CD's", 'link_rewrite' => 'cds'],
            ['name' => 'Vinyl', 'link_rewrite' => 'vinyl'],
            ['name' => "DVD's", 'link_rewrite' => 'dvds'],
            ['name' => 'Blu-ray', 'link_rewrite' => 'blu-ray']
        ];
        
        foreach ($categories as $catData) {
            // Check if category already exists
            $exists = Db::getInstance()->getValue('
                SELECT c.id_category 
                FROM ' . _DB_PREFIX_ . 'category c
                LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl ON c.id_category = cl.id_category
                WHERE cl.name = "' . pSQL($catData['name']) . '"
                AND cl.id_lang = ' . (int)$defaultLang
            );
            
            if (!$exists) {
                try {
                    $category = new Category();
                    $category->id_parent = $homeCategory;
                    $category->active = 1;
                    
                    foreach (Language::getLanguages(true) as $lang) {
                        $category->name[$lang['id_lang']] = $catData['name'];
                        $category->link_rewrite[$lang['id_lang']] = $catData['link_rewrite'];
                        $category->description[$lang['id_lang']] = '';
                    }
                    
                    if ($category->add()) {
                        $this->logError('Categorie aangemaakt: ' . $catData['name'], ['id' => $category->id]);
                    }
                } catch (Exception $e) {
                    $this->logError('Fout bij aanmaken categorie: ' . $catData['name'], ['error' => $e->getMessage()]);
                }
            }
        }
    }

    public function uninstall()
    {
        // Remove admin tab first
        try {
            $idTab = (int)Tab::getIdFromClassName('AdminMusicScanner');
            if ($idTab) {
                $tab = new Tab($idTab);
                $tab->delete();
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Music Scanner: Failed to delete tab - ' . $e->getMessage(), 2);
        }
        
        // Delete configuration
        Configuration::deleteByName('MUSIC_API_SOURCE');
        Configuration::deleteByName('DISCOGS_API_TOKEN');
        Configuration::deleteByName('BOLCOM_API_KEY');
        Configuration::deleteByName('BOLCOM_API_SECRET');
        Configuration::deleteByName('BOLCOM_PRICE_MARKUP');
        Configuration::deleteByName('BOLCOM_PRICE_MARKUP_TYPE');
        Configuration::deleteByName('BOLCOM_DEFAULT_STOCK');
        Configuration::deleteByName('BOLCOM_AUTO_SUBMIT');
        
        return parent::uninstall();
    }

    /**
     * Module configuration page
     */
    public function getContent()
    {
        $output = '';

        try {
            $this->logError('getContent aangeroepen', [
                'post' => $_POST,
                'get' => $_GET,
                'session' => isset($_SESSION) ? $_SESSION : []
            ]);

            // Process API settings form
            if (Tools::isSubmit('submitBolcomSettings')) {
                $apiSource = Tools::getValue('MUSIC_API_SOURCE');
                $discogsToken = Tools::getValue('DISCOGS_API_TOKEN');
                $apiKey = Tools::getValue('BOLCOM_API_KEY');
                $apiSecret = Tools::getValue('BOLCOM_API_SECRET');
                $priceMarkup = Tools::getValue('BOLCOM_PRICE_MARKUP');
                $priceMarkupType = Tools::getValue('BOLCOM_PRICE_MARKUP_TYPE');
                $defaultStock = Tools::getValue('BOLCOM_DEFAULT_STOCK');
                $autoSubmit = Tools::getValue('BOLCOM_AUTO_SUBMIT');
                
                $this->logError('API instellingen opgeslagen', [
                    'apiSource' => $apiSource,
                    'discogsToken' => !empty($discogsToken) ? '***' : 'leeg',
                    'bolcomKey' => !empty($apiKey) ? '***' : 'leeg',
                    'bolcomSecret' => !empty($apiSecret) ? '***' : 'leeg',
                    'priceMarkup' => $priceMarkup,
                    'priceMarkupType' => $priceMarkupType,
                    'defaultStock' => $defaultStock,
                    'autoSubmit' => $autoSubmit ? 'ja' : 'nee'
                ]);
                
                Configuration::updateValue('MUSIC_API_SOURCE', $apiSource);
                Configuration::updateValue('DISCOGS_API_TOKEN', $discogsToken);
                Configuration::updateValue('BOLCOM_API_KEY', $apiKey);
                Configuration::updateValue('BOLCOM_API_SECRET', $apiSecret);
                Configuration::updateValue('BOLCOM_PRICE_MARKUP', $priceMarkup);
                Configuration::updateValue('BOLCOM_PRICE_MARKUP_TYPE', $priceMarkupType);
                Configuration::updateValue('BOLCOM_DEFAULT_STOCK', $defaultStock);
                Configuration::updateValue('BOLCOM_AUTO_SUBMIT', $autoSubmit ? '1' : '0');
                
                $output .= $this->displayConfirmation($this->l('Instellingen opgeslagen'));
            }

            // Process product import form
            if (Tools::isSubmit('submitBolcomImport')) {
                $this->logError('Product import formulier verzonden', [
                    'ean' => Tools::getValue('ean_code')
                ]);
                
                // Check if API credentials are set based on selected source
                $apiSource = Configuration::get('MUSIC_API_SOURCE');
                $hasCredentials = false;
                
                if ($apiSource === 'discogs' && Configuration::get('DISCOGS_API_TOKEN')) {
                    $hasCredentials = true;
                } elseif ($apiSource === 'bolcom' && Configuration::get('BOLCOM_API_KEY') && Configuration::get('BOLCOM_API_SECRET')) {
                    $hasCredentials = true;
                }
                
                if (!$hasCredentials) {
                    $errorMsg = 'Configureer eerst je API credentials voor de geselecteerde bron';
                    $this->logError($errorMsg, ['apiSource' => $apiSource]);
                    $output .= $this->displayError($this->l($errorMsg));
                } else {
                    $ean = Tools::getValue('ean_code');
                    if ($ean) {
                        try {
                            $this->logError('Start product import', ['ean' => $ean]);
                            $result = $this->importProductByEAN($ean);
                            
                            if ($result['success']) {
                                $this->logError('Product succesvol geïmporteerd', [
                                    'ean' => $ean,
                                    'message' => $result['message']
                                ]);
                                $output .= $this->displayConfirmation($result['message']);
                            } else {
                                $this->logError('Fout bij importeren product', [
                                    'ean' => $ean,
                                    'error' => $result['message']
                                ]);
                                $output .= $this->displayError($result['message']);
                            }
                        } catch (Exception $e) {
                            $errorMsg = 'Fout bij importeren product: ' . $e->getMessage();
                            $this->logError($errorMsg, [
                                'ean' => $ean,
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                                'trace' => $e->getTraceAsString()
                            ]);
                            $output .= $this->displayError($this->l('Er is een fout opgetreden bij het importeren. Zie het logbestand voor meer informatie.'));
                        }
                    } else {
                        $errorMsg = 'Geen EAN code ingevuld';
                        $this->logError($errorMsg);
                        $output .= $this->displayError($this->l($errorMsg));
                    }
                }
            }

            return $output . $this->displayImportForm() . $this->displaySettingsForm() . $this->displayFooter();
            
        } catch (Exception $e) {
            $errorMsg = 'Ernstige fout in getContent: ' . $e->getMessage();
            $this->logError($errorMsg, [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->displayError($this->l('Er is een onverwachte fout opgetreden. Neem contact op met de beheerder en vermeld de datum/tijd van deze fout.'));
        }
    }

    /**
     * Display API settings form
     */
    protected function displaySettingsForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('API Instellingen'),
                    'icon' => 'icon-cogs'
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('API Bron'),
                        'name' => 'MUSIC_API_SOURCE',
                        'desc' => $this->l('Kies welke API je wilt gebruiken voor product informatie'),
                        'options' => [
                            'query' => [
                                ['id' => 'discogs', 'name' => 'Discogs (Gratis, grotere database)'],
                                ['id' => 'bolcom', 'name' => 'Bol.com (Vereist API credentials)']
                            ],
                            'id' => 'id',
                            'name' => 'name'
                        ]
                    ],
                    [
                        'type' => 'password',
                        'label' => $this->l('Discogs Personal Access Token'),
                        'name' => 'DISCOGS_API_TOKEN',
                        'size' => 50,
                        'desc' => $this->l('Je Discogs Personal Access Token (verkrijg gratis via https://www.discogs.com/settings/developers - klik "Generate new token")')
                    ],
                    [
                        'type' => 'password',
                        'label' => $this->l('Bol.com API Key'),
                        'name' => 'BOLCOM_API_KEY',
                        'size' => 50,
                        'desc' => $this->l('Je Bol.com API Key (optioneel)')
                    ],
                    [
                        'type' => 'password',
                        'label' => $this->l('Bol.com API Secret'),
                        'name' => 'BOLCOM_API_SECRET',
                        'size' => 50,
                        'desc' => $this->l('Je Bol.com API Secret (optioneel)')
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Prijs Markup (%)'),
                        'name' => 'BOLCOM_PRICE_MARKUP',
                        'size' => 10,
                        'desc' => $this->l('Percentage marge op Bol.com prijs (bijv. 20 voor +20%). Laat 0 voor geen markup.')
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Standaard Voorraad'),
                        'name' => 'BOLCOM_DEFAULT_STOCK',
                        'size' => 10,
                        'desc' => $this->l('Standaard voorraad bij import (bijv. 1)')
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Auto-Import na Barcode Scan'),
                        'name' => 'BOLCOM_AUTO_SUBMIT',
                        'is_bool' => true,
                        'desc' => $this->l('Automatisch importeren na barcode scan (zonder Enter te drukken)'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Aan')
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Uit')
                            ]
                        ]
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Instellingen Opslaan'),
                    'class' => 'btn btn-default pull-right'
                ]
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitBolcomSettings';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        
        $helper->fields_value['MUSIC_API_SOURCE'] = Configuration::get('MUSIC_API_SOURCE');
        $helper->fields_value['DISCOGS_API_TOKEN'] = Configuration::get('DISCOGS_API_TOKEN');
        $helper->fields_value['BOLCOM_API_KEY'] = Configuration::get('BOLCOM_API_KEY');
        $helper->fields_value['BOLCOM_API_SECRET'] = Configuration::get('BOLCOM_API_SECRET');
        $helper->fields_value['BOLCOM_PRICE_MARKUP'] = Configuration::get('BOLCOM_PRICE_MARKUP');
        $helper->fields_value['BOLCOM_DEFAULT_STOCK'] = Configuration::get('BOLCOM_DEFAULT_STOCK');
        $helper->fields_value['BOLCOM_AUTO_SUBMIT'] = Configuration::get('BOLCOM_AUTO_SUBMIT');

        return $helper->generateForm([$fields_form]);
    }

    /**
     * Display product import form
     */
    protected function displayImportForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Product Importeren'),
                    'icon' => 'icon-barcode'
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('EAN Code'),
                        'name' => 'ean_code',
                        'size' => 20,
                        'required' => true,
                        'desc' => $this->l('Voer de EAN/barcode in van het product (bijv. 8712177064014)')
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Product Importeren'),
                    'class' => 'btn btn-default pull-right'
                ]
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitBolcomImport';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        
        // Set default values for form fields
        $helper->fields_value['ean_code'] = '';

        // Add JavaScript for barcode scanner auto-submit
        $autoSubmit = Configuration::get('BOLCOM_AUTO_SUBMIT');
        $js = '<script type="text/javascript">
        $(document).ready(function() {
            var eanInput = $(\'input[name="ean_code"]\');
            var autoSubmit = ' . ($autoSubmit ? 'true' : 'false') . ';
            
            if (autoSubmit) {
                // Auto-submit after barcode scan (detects when input is filled quickly)
                var timeout;
                eanInput.on(\'input\', function() {
                    clearTimeout(timeout);
                    var value = $(this).val();
                    if (value.length >= 8) { // EAN is at least 8 digits
                        timeout = setTimeout(function() {
                            $(\'button[name="submitBolcomImport"]\').click();
                        }, 100); // Short delay to ensure full barcode is scanned
                    }
                });
            } else {
                // Submit on Enter key
                eanInput.on(\'keypress\', function(e) {
                    if (e.which === 13) { // Enter key
                        e.preventDefault();
                        $(\'button[name="submitBolcomImport"]\').click();
                    }
                });
            }
            
            // Clear input and refocus after submit
            $(\'form\').on(\'submit\', function() {
                setTimeout(function() {
                    eanInput.val(\'\').focus();
                }, 500);
            });
        });
        </script>';
        
        return $helper->generateForm([$fields_form]) . $js;
    }

    /**
     * Import product from Bol.com by EAN
     */
    protected function importProductByEAN($ean)
    {
        try {
            // Check if product already exists
            $existingProduct = $this->checkDuplicateProduct($ean);
            if ($existingProduct) {
                return [
                    'success' => false,
                    'message' => $this->l('Product bestaat al: ') . $existingProduct->name[(int)Configuration::get('PS_LANG_DEFAULT')] . ' (ID: ' . $existingProduct->id . ')'
                ];
            }

            // Search product on selected API
            $apiSource = Configuration::get('MUSIC_API_SOURCE');
            if ($apiSource === 'discogs') {
                $productData = $this->searchDiscogsProduct($ean);
            } else {
                $productData = $this->searchBolcomProduct($ean);
            }
            
            if (!$productData) {
                return [
                    'success' => false,
                    'message' => $this->l('Product niet gevonden op Bol.com met EAN: ') . $ean
                ];
            }

            // Create product in PrestaShop
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
            
            // Set product details
            $product->ean13 = $ean;
            
            // Apply price markup
            $basePrice = $productData['price'];
            $markup = (float)Configuration::get('BOLCOM_PRICE_MARKUP');
            if ($markup > 0) {
                $basePrice = $basePrice * (1 + ($markup / 100));
            }
            $product->price = round($basePrice, 2);
            
            $product->id_category_default = 2; // Home category
            $product->active = 1;
            
            // Add to default category
            $product->id_category = [2];

            if ($product->add()) {
                // Download and add product image
                if (!empty($productData['image_url'])) {
                    $this->addProductImage($product->id, $productData['image_url']);
                }

                // Set stock quantity
                $defaultStock = (int)Configuration::get('BOLCOM_DEFAULT_STOCK');
                StockAvailable::setQuantity($product->id, 0, $defaultStock);

                return [
                    'success' => true,
                    'message' => $this->l('Product succesvol geïmporteerd: ') . $productData['title']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $this->l('Fout bij aanmaken product in database')
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $this->l('Error: ') . $e->getMessage()
            ];
        }
    }

    /**
     * Search product on Bol.com Open API
     */
    protected function searchBolcomProduct($ean)
    {
        $apiKey = Configuration::get('BOLCOM_API_KEY');
        $apiSecret = Configuration::get('BOLCOM_API_SECRET');
        
        // Bol.com Open API endpoint
        $url = 'https://api.bol.com/catalog/v4/search?q=' . urlencode($ean) . '&format=json';
        
        // Create authorization header (Basic Auth)
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

        // Get first product
        $product = $data['products'][0];
        
        return [
            'title' => $product['title'] ?? 'Onbekend Product',
            'description' => $product['summary'] ?? '',
            'price' => isset($product['offerData']['offers'][0]['price']) 
                ? (float)$product['offerData']['offers'][0]['price'] 
                : 0,
            'image_url' => $product['images'][0]['url'] ?? '',
            'ean' => $ean
        ];
    }

    /**
     * Search product on Discogs API
     */
    protected function searchDiscogsProduct($ean)
    {
        $apiToken = Configuration::get('DISCOGS_API_TOKEN');
        
        // Discogs API endpoint - search by barcode
        $url = 'https://api.discogs.com/database/search?barcode=' . urlencode($ean) . '&type=release';
        
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

        $data = json_decode($response, true);
        
        if (empty($data['results'])) {
            return null;
        }

        // Get first result
        $release = $data['results'][0];
        
        // Build description from available data
        $description = '';
        if (!empty($release['year'])) {
            $description .= 'Release jaar: ' . $release['year'] . '<br>';
        }
        if (!empty($release['label'])) {
            $description .= 'Label: ' . implode(', ', $release['label']) . '<br>';
        }
        if (!empty($release['genre'])) {
            $description .= 'Genre: ' . implode(', ', $release['genre']) . '<br>';
        }
        if (!empty($release['style'])) {
            $description .= 'Stijl: ' . implode(', ', $release['style']) . '<br>';
        }
        if (!empty($release['format'])) {
            $description .= 'Formaat: ' . implode(', ', $release['format']) . '<br>';
        }
        
        return [
            'title' => $release['title'] ?? 'Onbekend Product',
            'description' => $description ?: 'Geen beschrijving beschikbaar',
            'price' => 0, // Discogs heeft geen prijzen, moet handmatig ingesteld worden
            'image_url' => $release['cover_image'] ?? $release['thumb'] ?? '',
            'ean' => $ean
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
                $path = _PS_PROD_IMG_DIR_ . $image->getImgFolder();
                
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
            // Log error but don't fail the import
            PrestaShopLogger::addLog('Bol.com Import: Failed to add image - ' . $e->getMessage(), 2);
        }
    }

    /**
     * Check if product already exists by EAN
     * 
     * @param string $ean EAN code to check
     * @return Product|false Returns Product object if found, false otherwise
     */
    protected function checkDuplicateProduct($ean)
    {
        // Search by EAN13
        $productId = Product::getIdByEan13($ean);
        
        if ($productId) {
            return new Product($productId);
        }
        
        return false;
    }

    /**
     * Remove duplicate tabs with the same class_name
     * Keeps only the first one and deletes the rest
     */
    private function removeDuplicateTabs()
    {
        $db = Db::getInstance();
        
        // Find all tabs with class_name 'AdminMusicScanner'
        $sql = 'SELECT id_tab FROM `' . _DB_PREFIX_ . 'tab` 
                WHERE class_name = "AdminMusicScanner" 
                ORDER BY id_tab ASC';
        
        $tabs = $db->executeS($sql);
        
        if ($tabs && count($tabs) > 1) {
            // Keep the first tab, delete the rest
            $firstTab = array_shift($tabs);
            
            foreach ($tabs as $tabData) {
                $tab = new Tab($tabData['id_tab']);
                $tab->delete();
            }
            
            $this->logError('Removed ' . count($tabs) . ' duplicate tab(s), kept tab ID: ' . $firstTab['id_tab']);
        }
    }

    /**
     * Display footer with developer credits
     */
    protected function displayFooter()
    {
        return '
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
        </div>';
    }
}
