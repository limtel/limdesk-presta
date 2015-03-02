<div class="wrapper">
    {if $msg == 'Wrong api key.' }
        <div class='alert alert-danger'>
            {$msg}
        </div>
    {/if}
    {if $msg == 'Configuration saved.' }
        <div class='alert alert-success'>
            {$msg}
        </div>
    {/if}
    {if $msg == 'Customers exported.' }
        <div class='alert alert-success'>
            {$msg}
        </div>
    {/if}
    <div>
        <img class="llogo" src="{$module_dir}/img/logo-lg.png">
    </div>
    <form method="POST" action="{$url}">
        <input class="linput" name="limdesk_api_key" value="{$api_key}" placeholder="{$api_key_string}">
        <div class="wselect">
            <select class="lselect lmargin" name="limdesk_widget_status">
                <option {if $widget_status=='on'} selected {/if} value="on">{$widget_on_string}</option>
                <option {if $widget_status!='on'} selected {/if} value="off">{$widget_off_string}</option>
            </select>
        </div>
        <div class="lcenter lmargin2x">
            <button class="lbtn" type="submit">
                {$save_string}
            </button>
        </div>
    </form>
    <hr/>
    <form method="POST" action="{$url}">
        <div class="export lcenter">
            <input type="hidden" name="export_customers">
            <button class="lbtn lbtn-export " type="submit">
                {$export_string}
            </button>
        </div>
    </form>
</div>