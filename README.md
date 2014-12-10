limdesk-presta
==============

Limdesk.com plugin for PrestaShop

Limdesk extension for Prestashop compatible with: 1.5+. (tested on 1.6.0.9). 
This extension integrates Limdesk.com with PrestaShop.


## Features

- clients export
- Limdesk's chat on your Prestashop frontpage
- all messages sent by Prestashop form will be added as tickets
- new orders will be added as tickets and sales
- when order status changes, private reply to ticket is added
- every new client that registers himself in Prestashop is added to Limdesk



## Installation

To install module, paste "limdesk" folder into "modules" folder in your 
Prestashop installation directory. The correct path should look like this:
prestashop_main_folder/modules/limdesk.

Please, don't change "limdesk" folder name. 
Module won't work if you do that.

> ###### Warning! During installation process, script will modify your database:
> ###### - new table "order_ticket"
> ###### - additional column to "customer" table

> ###### Added items will be removed when you decide to remove module. 

In your administration backend install the Limdesk plugin and go
to the module configuration page where you can insert your Limdesk Api Key.
Limdesk Api Key can be found on 
[this page](https://cloud.limdesk.com/settings/integration/api) 
after signin in to Limdesk.


## Usage
To turn on/off limdesk chat go to the module configuration page in your 
administration panel. 

To export clients press a proper button. It will be available after you submit 
Limdesk Api Key.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
