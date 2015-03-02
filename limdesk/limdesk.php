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
            'max' => '1.7');
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Limdesk');
        $this->description = $this->l('Limdesk integration.');

        $this->confirmUninstall =
                $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('MYMODULE_NAME')) {
            $this->warning = $this->l('Module name missing');
        }

    }
    public function install()
    {

        Configuration::updateValue(
            'limdesk_export_status',
            'false'
        );


        $sql=
                "CREATE TABLE IF NOT EXISTS `"
                ._DB_PREFIX_."order_ticket` "
                . "(`id_order` int(100), `ticket_number` int(100));";

        if (!$result=Db::getInstance()->Execute($sql)) {
            return false;
        }

        $sql= "ALTER TABLE `"._DB_PREFIX_.
                "customer` ADD COLUMN `id_limdesk` int(100)";

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

    public function prepareAddress($id) {
       $sql = "SELECT * FROM "._DB_PREFIX_."address WHERE id_address = '$id'";
        if ($row = Db::getInstance()->getRow($sql)) {
            $company = $row['company'];
            $firstname = $row['firstname'];
            $lastname = $row['lastname'];
            $street = $row['street'];
            $postcode = $row['postcode'];
            $city = $row['city'];
            $phone = $row['phone'];
            $phone_mobile = $row['phone_mobile'];
        }
        $address =
                $company."\n"
                .$firstname.' '.$lastname."\n"
                .$street."\n"
                .$postcode.' '.$city."\n"
                .$phone;
        return $address;
    }

    private function retriveWidgetKey($apiKey)
    {
        $arr = $this->pushData($this->__url.'widget?key=', $apiKey, 'get');
        $limdesk_widget_key = $arr['hash'];
        if ($limdesk_widget_key) {
            Configuration::updateValue(
                'limdesk_widget_key',
                $limdesk_widget_key
            );
            return true;
        }
        return false;
    }

    public function getContent()

    {
        if (Tools::isSubmit('limdesk_api_key')) {

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

            $limdesk_api_key = Configuration::get('limdesk_api_key');

            if ($limdesk_api_key) {
                $status = $this->retriveWidgetKey($limdesk_api_key);
            }

            if ($status == false) {
                $msg = $this->l('Wrong api key.');
            }
            else {            
                $msg = $this->l('Configuration saved.');
            }
        }

        if (Tools::isSubmit('export_customers')) {
            $sql = 'SELECT CONCAT(firstname,'."' '".',lastname)AS name,
                email FROM '._DB_PREFIX_.'customer';

            $results['clients'] = Db::getInstance()->ExecuteS($sql);
            $limdesk_api_key=Configuration::get('limdesk_api_key');

            $arr =  $this->pushData(
                $this->__url.'clients/multi?key='
                .$limdesk_api_key, $results, 'post'
            );
            $msg = $this->l('Clients successfully exporterd to Limdesk');
            
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
                }
            }

        }


        $this->smarty->assign(
                array (
                    'msg' => $msg,
                    'api_key' => Configuration::get('limdesk_api_key'),
                    'widget_status' => Configuration::get('limdesk_widget_status'),
                    'export_status' => Configuration::get('limdesk_export_status'),
                    'url' => $_SERVER['REQUEST_URI'],
                    'api_key_string' => $this->l('Api key'),
                    'widget_on_string' => $this->l('Widget on'),
                    'widget_off_string' => $this->l('Widget off'),
                    'save_string' => $this->l('Save'),
                    'export_string' => $this->l('Export customers')
                )
        );
        return $this->display(__FILE__, 'views/templates/admin/configuration.tpl');
    }



    public function hookDisplayBackOfficeHeader($params)
    {
        $this->context->controller->addCSS(
                ($this->_path) . 'css/style.css', 'all'
        );

        $this->context->controller->addCSS(
                ($this->_path) . 'css/switch.css', 'all'
        );

        $this->context->controller->addJS(array(
            _PS_JS_DIR_ . 'fileuploader.js',
        ));
    }

    public function hookDisplayFooter ($params)
    {
        $limdesk_widget_status = Configuration::get('limdesk_widget_status');
        $limdesk_widget_key = Configuration::get('limdesk_widget_key');
        if ($limdesk_widget_status == 'on') {
        ?>
            <div id="limdesk-widget" data-applet-url="//cloud.limdesk.com/applet?callback=?" data-hash="<?= $limdesk_widget_key ?>">
            </div>
            <script type="text/javascript" src="//cloud.limdesk.com/widget/js/widget.js">
            </script>
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


    private function updateCustomerLimdeskId($email, $limdeskId)
    {
        $sql = 'UPDATE '._DB_PREFIX_."customer SET id_limdesk= '$limdeskId' WHERE email= '$email'";
        if (!Db::getInstance()->execute($sql)) {
            return false;
        }
        else {
            return true;
        }
    }
    
    private function getLimdeskIdByEmail($email) 
    {
        $sql = "SELECT * FROM "._DB_PREFIX_."customer WHERE email = '$email'";
        if ($row = Db::getInstance()->getRow($sql)) {
            $id_limdesk = $row['id_limdesk'];
            return $id_limdesk;
        }
        return false;           
    }

    private function retriveLimdeskIdByEmail($email) 
    {
        $apiKey = Configuration::get('limdesk_api_key');
        $data = 'query='.$email.'&key='.$apiKey;
        $customer = $this->pushData($this->__url.'clients/get_by_email?',$data, 'get');
        return $customer['client']['id'];
    }
    
    private function pushCustomer($customer)
    {
        $limdesk_api_key = Configuration::get('limdesk_api_key');
        $customer_name = 
                $customer->firstname.' '.$customer->lastname;

        $data = array (
            'name'=>  $customer_name,
            'nippesel'=>'',
            'phone'=>'' ,
            'email'=>  $customer->email,
            'adress'=>''
        );

        $arr = $this->pushData($this->__url.'clients?key='. $limdesk_api_key, $data, 'post');

        if ($arr['client']['id']) {
            $this->updateCustomerLimdeskId($customer->email, $arr['client']['id']);
        }        
    }

    public function hookActionValidateOrder($params)
    {
        $limdesk_api_key = Configuration::get('limdesk_api_key');
        $customerEmail = $params['customer'] ->email;
        $id_limdesk = $this->getLimdeskIdByEmail($customerEmail);
        
        if (empty($id_limdesk)){
            $id_limdesk = $this->retriveLimdeskIdByEmail($params['customer'] ->email);
            if ($id_limdesk == NULL) {
                $this->pushCustomer($params['customer']);
            }
            else {
                $this->updateCustomerLimdeskId($customerEmail, $id_limdesk);
            }
        }

        $address = $this->prepareAddress($params['cart']->id_address_delivery);
        $id_order = $params['order']->id;
        $payment_type = $params['order']->payment;
        $order_number = $params['order']->reference;

        $sql = "SELECT * FROM "._DB_PREFIX_."order_detail WHERE id_order = '$id_order'";
        if ($rows = Db::getInstance()->ExecuteS($sql)) {
            foreach ($rows as $row) {
                $product_name = $row['product_name'];
                $product_quantity = $row['product_quantity'];
                $product_price = round($row['unit_price_tax_incl'], 2);

                $data = array (
                    'client_id'=> $id_limdesk,
                    'name'=> $product_name.' / '.$this->l('Order number').': '.$order_number,
                    'price'=> $product_price,
                    'amount'=> $product_quantity
                );

                $this->pushData($this->__url.'sales?key='.$limdesk_api_key, $data, 'post');
            }
        }

        $content =
                $this->l('Order number').": ".$order_number."\n".
                $this->l('Payment method').": ".$payment_type."\n".
                $address;

        $title = '[Prestashop] '.$this->l('New order');
        $data = array (
            'title'=> $title,
            'content'=> $content,
            'reportedBy'=>'' ,
            'client_id'=> $id_limdesk
        );

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