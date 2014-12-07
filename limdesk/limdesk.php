<?php


if (!defined('_PS_VERSION_')) {
    exit; 
}
class Limdesk extends Module
{
    private $__url = 'https://cloud.limdesk.com/api/v1/';

    public function __construct()
    {
        $this->name = 'limdesk';
        $this->tab = 'other';
        $this->version = '1.0.0';
        $this->author = 'Limdesk';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.5', 
            'max' => _PS_VERSION_); 
        $this->bootstrap = true;
 
        parent::__construct();
 
        $this->displayName = $this->l('Limdesk');
        $this->description = $this->l('Limdesk integration.');
 
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
 
        if (!Configuration::get('MYMODULE_NAME')) {      
            $this->warning = $this->l('Brak nazwy modułu.'); 
        }

    }
    public function install()
    {

        Configuration::updateValue(
            'limdesk_export_status',
            'false'
        );    

        $sql= "CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."order_ticket` (`id_order` int(100), `ticket_number` int(100));";
        if (!$result=Db::getInstance()->Execute($sql)) {
            return false; 
        }

        $sql= "ALTER TABLE `"._DB_PREFIX_."customer` ADD COLUMN `id_limdesk` int(100)";
     
        if (!$result=Db::getInstance()->Execute($sql)) {
            return false; 
        }

        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL); 
        }
 
        if (!parent::install() 
            || !$this->registerHook('Home') 
            || !$this->registerHook('displayFooter') 
            || !$this->registerHook('displayBackOfficeHeader') 
            || !$this->registerHook('actionCustomerAccountAdd') 
            || !$this->registerHook('ActionValidateOrder') 
            || !$this->registerHook('actionObjectCustomerMessageAddAfter') 
            || !$this->registerHook('actionOrderStatusPostUpdate') 
            || !Configuration::updateValue('MYMODULE_NAME', 'limdesk')
        ) {
            return false; 
        }
 
        return true;
    }


    public function uninstall()
    {
        $sql= "ALTER TABLE `"._DB_PREFIX_."customer` DROP COLUMN `id_limdesk`";
        if (!$result=Db::getInstance()->Execute($sql)) {
            return false; 
        }

        $sql= "DROP TABLE `"._DB_PREFIX_."order_ticket`";
        if (!$result=Db::getInstance()->Execute($sql)) {
            return false; 
        }

        Db::getInstance()->delete(
            _DB_PREFIX_.'configuration', "name = 'limdesk_widget_key'"
        );
        Db::getInstance()->delete(
            _DB_PREFIX_.'configuration', "name = 'limdesk_api_key'"
        );
        Db::getInstance()->delete(
            _DB_PREFIX_.'configuration', "name = 'limdesk_widget_status'"
        );
        Db::getInstance()->delete(
            _DB_PREFIX_.'configuration', "name = 'limdesk_export_status'"
        );

        if (!parent::uninstall() 
            || !Configuration::deleteByName('MYMODULE_NAME')
        ) {
            return false; 
        }
 
        return true;
    }

    public function pushData ($url, $data, $method) 
    {
        $ch  = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if ($method == 'post') {
            $data = json_encode($data);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        if ($method == 'get') {
            curl_setopt($ch, CURLOPT_URL, $url.$data);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $return_data = curl_exec($ch);
        curl_close($ch);
        $arr = json_decode($return_data, true);
        return $arr;
    } 



    public function getContent() 
    {                                                                                     
        if (Tools::isSubmit('save_form')) {

            if (Tools::getValue('limdesk_api_key') != Configuration::get('limdesk_api_key')) {
                Configuration::updateValue(
                    'limdesk_export_status',
                    'false'
                );              
            }             

            Configuration::updateValue(
                'limdesk_api_key',
                Tools::getValue('limdesk_api_key')
            );

            Configuration::updateValue(
                'limdesk_widget_status',
                Tools::getValue('limdesk_widget_status')
            );
            $limdesk_api_key=Configuration::get('limdesk_api_key');
            if ($limdesk_api_key) {
                $arr = $this->pushData($this->__url.'widget?key=', $limdesk_api_key, 'get');
                $limdesk_widget_key = $arr['hash'];
                if ($limdesk_widget_key) {
                    Configuration::updateValue(
                        'limdesk_widget_key',
                        $limdesk_widget_key
                    );       
                }
            }     
            $output = null;  
            $output .= $this->displayConfirmation(
                $this->l('Configuration saved.')
            );
        }

        if (Tools::isSubmit('export_clients')) {
            $sql = 'SELECT CONCAT(firstname,'."' '".',lastname)AS name, 
                email FROM '._DB_PREFIX_.'customer';

            $results['clients'] = Db::getInstance()->ExecuteS($sql);
            $limdesk_api_key=Configuration::get('limdesk_api_key');

            $arr =  $this->pushData(
                $this->__url.'clients/multi?key='
                .$limdesk_api_key, $results, 'post'
            );     
            
            foreach ($arr['clients'] as $client) {
                $id_limdesk = $client['id'];
                $email = $client['email'];
                $sql = 'UPDATE '._DB_PREFIX_."customer SET id_limdesk= 
                    '$id_limdesk' WHERE email= '$email'";
                if (!Db::getInstance()->execute($sql)) {
                    $output = null;  
                    $output .= $this->displayConfirmation($this->l('Error'));            
                    d('Error');
                } else {
                    Configuration::updateValue(
                        'limdesk_export_status',
                        'true'
                    );
                    $output = null;  
                    $output .= $this->displayConfirmation(
                        $this->l('Clients successfully exporterd to Limdesk')
                    );     
                }
            } 

        }         
                                                                                                                  
        $this->_generateForm();
        if (isset($output)) {
            return $output.$this->_html;
        } else {
            return $this->_html;
        }
    }
                                                                                                
    private function _generateForm() 
    {
                                                                                                
        $limdesk_api_key=Configuration::get('limdesk_api_key');
        $limdesk_widget_status=Configuration::get('limdesk_widget_status');
        $limdesk_export_status=Configuration::get('limdesk_export_status');
                                                                                       
        $this->_html .= '<form action="'.$_SERVER['REQUEST_URI'].'" method="post">';
        $this->_html .= '<div class="col-md-4">';  
        $this->_html .= '<div class="row" >';
        $this->_html .= '<label>'.$this->l('Limdesk Api Key: ').'</label>';
        $this->_html .= '<input  class="form-control" type="text" name="limdesk_api_key" value="'. $limdesk_api_key.'" >';
        $this->_html .= '</div>';
    
        if ($limdesk_api_key) {
            $this->_html .= '<div class="row" style="margin-top: 10px;">';
            $this->_html .= '<label>'.$this->l('Limdesk Widget Status: ').'</label>';
            $this->_html .= '<select name="limdesk_widget_status" >';
            if ($limdesk_widget_status == "on") {
                $this->_html.= '<option selected value="on">On</option>';
                $this->_html.= '<option value="off">Off</option>';
            } else {
                $this->_html.= '<option value="on">On</option>';
                $this->_html.= '<option selected value="off">Off</option>';
            }
            $this->_html .= '</select>';
            $this->_html .= '</div>';
        }
        $this->_html .= '<div class="row">';
        $this->_html .= '<div class="pull-left">';
        $this->_html .= '<input class="btn btn-primary" style="margin-top: 20px;" type="submit" name="save_form" ';
        $this->_html .= 'value="'.$this->l('Save configuration').'" class="button" />';
        $this->_html .= '</div>';
        if ($limdesk_api_key && $limdesk_export_status=='false') {
            $this->_html .= '<div class="pull-right">';    
            $this->_html .= '<input class="btn btn-warning" style="margin-top: 20px;" type="submit" name="export_clients" ';
            $this->_html .= 'value="'.$this->l('Export clients').'" class="button" />';
            $this->_html .= '</div>';
        }
        $this->_html .= '</div>';
        $this->_html .= '</div>'; 
        $this->_html .= '</form>';
    
    }

    public function hookDisplayBackOfficeHeader($params) 
    {
        $_controller = $this->context->controller;
        $_controller->addJs($_controller->addJs($this->_path . 'js/limdesk.js'));
        $_controller->addJs($_controller->addCss($this->_path . 'css/custom.js'));
    }

    public function hookDisplayFooter ($params) 
    {
        $limdesk_widget_status = Configuration::get('limdesk_widget_status');
        $limdesk_widget_key = Configuration::get('limdesk_widget_key');
        if ($limdesk_widget_status == 'on') {
        ?>
            <div id="limdesk-widget" data-applet-url="//cloud.limdesk.com/applet?callback=?" data-hash="<?= $limdesk_widget_key ?>"></div><script type="text/javascript" src="//cloud.limdesk.com/widget/js/widget.js"></script>
        <?php
        }    
    }   

    public function hookActionCustomerAccountAdd($params) 
    {
        $limdesk_api_key = Configuration::get('limdesk_api_key');
        $customer_name = $params['newCustomer']->firstname.' '.$params['newCustomer']->lastname;
        $customer_email = $params['newCustomer']->email;
        $data = array ('name'=>  $customer_name, 'nippesel'=>'', 'phone'=>'' ,'email'=>  $customer_email, 'adress'=>'');

        $arr = $this->pushData($this->__url.'clients?key='. $limdesk_api_key, $data, 'post');

        if ($arr['client']['id']) {
            $id_limdesk = $arr['client']['id'];
            $sql = 'UPDATE '._DB_PREFIX_."customer SET id_limdesk= '$id_limdesk' WHERE email= '$customer_email'";
            if (!Db::getInstance()->execute($sql)) {
                die('Error'); 
            }
        }
    }


    public function hookActionValidateOrder($params)
    {
        $limdesk_api_key = Configuration::get('limdesk_api_key');
        $customer_email = $params['customer'] ->email;

        $sql = "SELECT * FROM "._DB_PREFIX_."customer WHERE email = '$customer_email'";
        if ($row = Db::getInstance()->getRow($sql)) {
            $id_limdesk = $row['id_limdesk'];
        } else {
            $id_limdesk = '-1';
        }

        $id_order = $params['order']->id;
        $payment_type = $params['order']->payment;
        $order_number = $params['order']->reference;

        $sql = "SELECT * FROM "._DB_PREFIX_."order_detail WHERE id_order = '$id_order'";
        if ($rows = Db::getInstance()->ExecuteS($sql)) {
            foreach ($rows as $row) {
                $product_name = $row['product_name'];
                $product_quantity = $row['product_quantity'];
                $product_price = round($row['unit_price_tax_incl'], 2);
                $data = array ('client_id'=> $id_limdesk, 'name'=> $product_name.' / zamówienie nr: '.$order_number, 'price'=> $product_price, 'amount'=> $product_quantity);

                $this->pushData($this->__url.'sales?key='.$limdesk_api_key, $data, 'post');
            }
        }

        $content 
            = "Numer zamówienia: ".$order_number."\n".
            "Rodzaj płatności: ".$payment_type."\n";
        $title = '[Prestashop] Nowe zamówienie';
        $data = array ('title'=> $title, 'content'=> $content, 'reportedBy'=>'' , 'client_id'=> $id_limdesk);

        $arr = $this->pushData($this->__url.'tickets?key='.$limdesk_api_key, $data, 'post');

        $ticket_number = $arr['ticket']['number'];

        Db::getInstance()->insert(
            'order_ticket', array(
                'id_order' => $id_order,
                'ticket_number' => $ticket_number,
            )
        );
    }

    public function hookActionObjectCustomerMessageAddAfter($params) 
    {
        $object = $params['object'];
        $cookie = $params['cookie'];

        $limdesk_api_key = Configuration::get('limdesk_api_key');

        $id_customer_thread = $object->id_customer_thread;

        $sql = "SELECT * FROM "._DB_PREFIX_."customer_thread WHERE id_customer_thread = '$id_customer_thread'";
        if ($row = Db::getInstance()->getRow($sql)) {
            $customer_email = $row['email'];
            $id_order = $row['id_order'];
            if ($id_order != '0') {
                $sql = "SELECT * FROM "._DB_PREFIX_."orders WHERE id_order = '$id_order'";
                if ($row = Db::getInstance()->getRow($sql)) {
                    $order_number = $row['reference'];
                }
            }
        }

        $sql = "SELECT * FROM "._DB_PREFIX_."customer WHERE email = '$customer_email'";
        if ($row = Db::getInstance()->getRow($sql)) {
            $id_limdesk = $row['id_limdesk'];
        } else {
            $data = array ('name'=> $customer_email, 'nippesel'=>'', 'phone'=>'' ,'email'=>  $customer_email, 'adress'=>'');

            $arr = $this->pushData($this->__url.'clients?key='. $limdesk_api_key, $data, 'post');

            if ($arr['client']['id']) {
                $id_limdesk = $arr['client']['id'];
                $sql = 'UPDATE '._DB_PREFIX_."customer SET id_limdesk= '$id_limdesk' WHERE email= '$customer_email'";
                if (!Db::getInstance()->execute($sql)) {
                    die('Error'); 
                }
            }  
        }

        $message = $object->message;
        if (isset($order_number)) {
            $title = '[Prestashop] Pytanie do zamówienia: '.$order_number;
        } else {
            $title = '[Prestashop] Pytanie od klienta';
        }

        $data = array ('title'=> $title, 'content'=> $message, 'reportedBy'=>'' , 'client_id'=> $id_limdesk);

        $this->pushData($this->__url.'tickets?key='.$limdesk_api_key, $data, 'post');
    }

    public function hookActionOrderStatusPostUpdate ($params) 
    {
        $limdesk_api_key = Configuration::get('limdesk_api_key');
        $new_order_status = $params['newOrderStatus']->name;
        $id_order = $params['id_order'];
        $sql = "SELECT * FROM "._DB_PREFIX_."order_ticket WHERE id_order = '$id_order'";
        if ($row = Db::getInstance()->getRow($sql)) {
            $ticket_number = $row['ticket_number'];
        }
        if ($ticket_number) {    
            $content = 'Status: '.$new_order_status;
            $data = array ('content'=> $content, 'type'=>'1');

            $this->pushData($this->__url."tickets/$ticket_number/answer?key=".$limdesk_api_key, $data, 'post');
        }
    }

}

?>