limdesk-presta
==============

Limdesk.com plugin for PrestaShop

Limdesk extension for Prestashop compatible with: 1.5+. (tested on 1.6.0.9). 
This extension integrate's Limdesk.com with PrestaShop.


## Features

- possibility to export client's
- Limdesk's chat on your pestashop frontpage
- all messages sent by prestashop form will be add as tickets
- new orders will be added as ticket's and sale's
- when order status changes, private reply to ticket will be added
- when a client will register's in Prestashop, he will added in Limdesk



## Installation

To install module, paste "limdesk" folder into "modules" folder in your 
prestashop installation directory. So the correct path should look like this:
prestashop_main_folder/modules/limdesk.

Please, don't change "limdesk" folder name. 
Module won't work if you do that.

> ###### Warning! During installation process, script will modify your database:
> ###### - new table "order_ticket"
> ###### - additional column to "customer" table

> ###### Added items will be removed when you decide to remove module. 

In your administration backend install the Limdesk plugin and go to the 
module configuration page where you can insert your Limdesk Api Key.
Limdesk Api Key can be found on 
[this page](https://cloud.limdesk.com/settings/integration/api) 
after signin in to Limdesk.


## Usage
To turn on/off limdesk chat go to the module configuration page in your 
administration panel. 

To export client's press proper button. It will be available after you submit 
Limdesk Api Key.