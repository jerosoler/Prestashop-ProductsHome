<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;
use PrestaShop\PrestaShop\Adapter\Category\CategoryProductSearchProvider;
use PrestaShop\PrestaShop\Adapter\Search\SearchProductSearchProvider;
use PrestaShop\PrestaShop\Adapter\Product\ProductDataProvider;

use PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;
use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;
use PrestaShop\PrestaShop\Core\Product\ProductListingPresenter;
use PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchResult;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;

require_once _PS_MODULE_DIR_.'productosportada/classes/productos.php';


class ProductosPortada extends Module  implements WidgetInterface
{
  private $templateFile;
  public function __construct()
  {



    $this->name = 'productosportada';

    $this->version = '1.0.0';
    $this->author = 'Jero Soler';
    $this->need_instance = 0;

    $this->bootstrap = true;

    parent::__construct();

    $this->displayName = $this->l('Productos Portada');
    $this->description = $this->l('Poner productos en portada');
    $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);

    $this->templateFile = 'module:productosportada/productosportada.tpl';

  }




public function install()
{
    return  parent::install() &&
        $this->installDB() &&
        $this->registerHook('displayHome') &&
        $this->installFixtures();
}

public function uninstall()
{
    return parent::uninstall() && $this->uninstallDB();
}

public function installDB()
{
    $return = true;


    $return &= Db::getInstance()->execute('
                  CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'productos_portada` (
                  `id_info` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `id_shop` int(10) unsigned DEFAULT NULL,
                  PRIMARY KEY (`id_info`)
              ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8 ;'
          );

          $return &= Db::getInstance()->execute('
                  CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'productos_portada_lang` (
                  `id_info` INT UNSIGNED NOT NULL,
                  `id_lang` int(10) unsigned NOT NULL ,
                  `text` text NOT NULL,
                  PRIMARY KEY (`id_info`, `id_lang`)
              ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8 ;'
          );
    return $return;
}

public function uninstallDB($drop_table = true)
{
    $ret = true;
    if ($drop_table) {
      $ret &=  Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'productos_portada`') && Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'productos_portada_lang`');

    }

    return $ret;
}

public function installFixtures()
  {
      $return = true;
      $tab_texts = array(
          array(
              'text' => '<h3>Custom Text Block</h3>
              <h3>jajajajjajsjdasjdasjdj jdjasdasd test</h3>
              <p>adasdasda</p>
              <p></p>
              <p>[[6:41,7,6:41]]</p>
              <p></p>
              <p>Más textoasdasdasdas :as </p>
              <p>adasdasda</p>
              <p></p>
              <p>[[6:41,7,6:32]]</p>
              <p>productosasdasd</p>
              <p></p>'
          ),
      );

      $shops_ids = Shop::getShops(true, null, true);

      foreach ($tab_texts as $tab) {
          $info = new ProductsADD();
          foreach (Language::getLanguages(false) as $lang) {
              $info->text[$lang['id_lang']] = $tab['text'];
          }
          foreach ($shops_ids as $id_shop) {
              $info->id_shop = $id_shop;
              $return &= $info->add();
          }
      }

      return $return;
  }




public function getContent()
{
  $output = null;

    if (Tools::isSubmit('submit'.$this->name))
    if (!Tools::getValue('text_'.(int)Configuration::get('PS_LANG_DEFAULT'), false)) {
                $output = $this->displayError($this->trans('Please fill out all fields.', array(), 'Admin.Notifications.Error')) . $this->renderForm();
            } else {
                $update = $this->processSaveProductos();

                if (!$update) {
                    $output = '<div class="alert alert-danger conf error">'
                        .$this->trans('An error occurred on saving.', array(), 'Admin.Notifications.Error')
                        .'</div>';
                }

                $this->_clearCache($this->templateFile);
            }
    return $output.$this->displayForm();
}


public function processSaveProductos()
    {
        $info = new ProductsADD(Tools::getValue('id_info', 1));

        $text = array();
        $languages = Language::getLanguages(false);
        foreach ($languages as $lang) {
            $text[$lang['id_lang']] = Tools::getValue('text_'.$lang['id_lang']);
        }

        $info->text = $text;

        if (Shop::isFeatureActive() && !$info->id_shop) {
            $saved = true;
            $shop_ids = Shop::getShops();
            foreach ($shop_ids as $id_shop) {
                $info->id_shop = $id_shop;
                $saved &= $info->add();
            }
        } else {
          $saved = $info->save();
        }

        return $saved;
    }


public function displayForm()
{
    // Get default language
    $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

    // Init Fields form array
    $fields_form[0]['form'] = array(
        'tinymce' => true,
        'legend' => array(
            'title' => $this->l('Settings'),
        ),
        'input' => array(
          'id_info' => array(
                  'type' => 'hidden',
                  'name' => 'id_info'
              ),
              'content' => array(
                    'type' => 'textarea',
                    'label' => $this->l('Text block'),
                    'lang' => true,
                    'name' => 'text',
                    'cols' => 40,
                    'rows' => 10,
                    'class' => 'rte',
                    'autoload_rte' => true,
                ),

        ),
        'submit' => array(
            'title' => $this->l('Save'),
            'class' => 'btn btn-default pull-right'
        )
    );


    $helper = new HelperForm();

    // Module, token and currentIndex
    $helper->module = $this;
    $helper->name_controller = $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

    foreach (Language::getLanguages(false) as $lang) {
              $helper->languages[] = array(
                  'id_lang' => $lang['id_lang'],
                  'iso_code' => $lang['iso_code'],
                  'name' => $lang['name'],
                  'is_default' => ($default_lang == $lang['id_lang'] ? 1 : 0)
              );
          }

    // Language
    $helper->default_form_language = $default_lang;
    $helper->allow_employee_form_lang = $default_lang;

    // Title and toolbar
    $helper->title = $this->displayName;
    $helper->show_toolbar = true;        // false -> remove toolbar
    $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
    $helper->submit_action = 'submit'.$this->name;
    $helper->toolbar_btn = array(
        'save' =>
        array(
            'desc' => $this->l('Save'),
            'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
            '&token='.Tools::getAdminTokenLite('AdminModules'),
        ),
        'back' => array(
            'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
            'desc' => $this->l('Back to list')
        )
    );

    // Load current value
  $helper->fields_value = $this->getFormValues();

    return $helper->generateForm($fields_form);
}


public function getFormValues()
    {
        $fields_value = array();
        $id_info = 1;

        foreach (Language::getLanguages(false) as $lang) {
            $info = new ProductsADD((int)$id_info);
            $fields_value['text'][(int)$lang['id_lang']] = $info->text[(int)$lang['id_lang']];
        }

        $fields_value['id_info'] = $id_info;

        return $fields_value;
    }





public function renderWidget($hookName = null, array $configuration = [])
  {
      if (!$this->isCached($this->templateFile, $this->getCacheId('productosportada'))) {
          $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));
      }

      return $this->fetch($this->templateFile, $this->getCacheId('productosportada'));


  }


  protected function multiexplode($delimiters,$string) {

      $ready = str_replace($delimiters, $delimiters[0], $string);
      $launch = explode($delimiters[0], $ready);
      return  $launch;
  }

  public function getWidgetVariables($hookName = null, array $configuration = [])
  {
      $sql = 'SELECT r.`id_info`, r.`id_shop`, rl.`text`
          FROM `'._DB_PREFIX_.'productos_portada` r
          LEFT JOIN `'._DB_PREFIX_.'productos_portada_lang` rl ON (r.`id_info` = rl.`id_info`)
          WHERE `id_lang` = '.(int)$this->context->language->id.' AND  `id_shop` = '.(int)$this->context->shop->id;

    //  $products = $this->getProducts();

      $texto = Db::getInstance()->getRow($sql);
      $productosver = $this->multiexplode(array("[","]"),$texto['text']);
      //print_r($productosver);



      $superarray = [];
      $claves = preg_split("/\[(.*)\]/", $texto['text'], -1,  PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

      foreach($claves as $linea) {

        $findme = "[";
        $pos = strpos($linea, $findme);
        if($pos === 0) {
          $linea = substr($linea, 1, -1);
          $linea = $this->getProductsNew($linea);

          $arrayproductos = array( 0 => "producto", 1 => $linea);
          array_push($superarray, $arrayproductos);
        } else {
          $arraytexto = array( 0 => "texto", 1 => $linea);
          array_push($superarray, $arraytexto);
        }
      }
      /*print_r("<br>SUPERARRAY<br>");
      print_r($superarray);*/


    /*  return array(
          'products' => $products,
          'cms_infos' => Db::getInstance()->getRow($sql),
      );
      */

      return array( 'products' => $superarray);
  }

protected function getProductsNew($busqueda) {
  $searchProvider = new SearchProductSearchProvider(
      $this->context->getTranslator()
  );

  $context = new ProductSearchContext($this->context);


  $arraysearch = [];
  $busqueda  = explode(",", $busqueda);
  foreach($busqueda as $linea) {
    $linea = explode(":", $linea);

    $cuantos = count($linea);
    if($cuantos === 2) {
      $productocompleto = array( 'id_product' => $linea[0], 'id_product_attribute' => $linea[1]);
      array_push($arraysearch, $productocompleto);


    } else {
      $productocompleto = array( 'id_product' => $linea);
      array_push($arraysearch, $productocompleto);
    }
  }


  //$produstsearch = array( 0 => Array ( "id_product" => "6", "id_product_attribute" => "41" ) );
$produstsearch = $arraysearch;
//  print_r($produstsearch);


  $assembler = new ProductAssembler($this->context);

  $presenterFactory = new ProductPresenterFactory($this->context);
  $presentationSettings = $presenterFactory->getPresentationSettings();

  $presenter = new ProductListingPresenter(

      new ImageRetriever(
          $this->context->link
      ),
      $this->context->link,
      new PriceFormatter(),
      new ProductColorsRetriever(),
      $this->context->getTranslator()
  );

  $products_for_template = [];

  foreach ($produstsearch as $rawProduct) {
      $products_for_template[] = $presenter->present(
          $presentationSettings,
          $assembler->assembleProduct($rawProduct),
          $this->context->language

      );
      //print_r($assembler->assembleProduct($rawProduct));
  }
  //print_r($result->getProducts());
  //print_r($products_for_template);
  return $products_for_template;


}






}
