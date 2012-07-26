jQuery(function($){
	$("select[name='templates_new']").change(function(){
		if(this.value != 0)
		{
			window.location = this.value;
		}
	});
});