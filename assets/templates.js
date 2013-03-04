jQuery(function($){
    $("select[name='templates_new']").change(function(){
        if(this.value != 0)
        {
            window.location = this.value;
        }
    });
    templateBindFunctions();

    var $link = $('p.notice a[accesskey="a"]');
    if($link.length == 1)
    {
        $('p.notice a[accesskey="a"]')[0].href = '/symphony/extension/templates/publish/';
    }
});

function templateBindFunctions()
{
    var $ = jQuery;
    $('td.templates-actions a.down, td.templates-actions a.up').click(function(e){
        e.preventDefault();
        $('form').load(this.href + ' form>*', function(){
            templateBindFunctions();
        });
    });
}