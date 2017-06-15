d_shopunity_widget = {
    setting: {
        'http' : '',
        'class' : '.d_shopunity_widget',
        'extension_id' : '', //set admin url with token
        'token': ''
    },

    loadExtension: function($extension_id){
        $( this.setting.class ).load( this.setting.http+"index.php?route=d_shopunity/extension/show_thumb&extension_id="+$extension_id+"&token="+this.setting.token );
    },

    init: function(setting){
        this.setting = $.extend({}, this.setting, setting);
        
        if(!this.setting.token){
            console.error('d_shopunity_widget: No token specified in setting');
        }

        if(!this.setting.extension_id){
            console.error('d_shopunity_widget: No extension_id specified in setting');
        }

        this.render();
    },

    render: function(){
        this.loadExtension(this.setting.extension_id);
    }

}