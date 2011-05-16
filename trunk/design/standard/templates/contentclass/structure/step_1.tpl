<form action="" method="post">

	<div class="block">
		<label>Parent node ID:</label>
		<input type="text" value="2" name="parentNodeID" />
	</div>
	
	<div class="block">
		<label>Dummy content parent node ID:</label>
		<input type="text" value="" name="dummyParentNodeID" />
	</div>
	
	{ezscript_require( 'ezjsc::jquery' )}
	<script id="tinymce_script_loader" type="text/javascript" src={"javascript/tiny_mce_jquery.js"|ezdesign} charset="utf-8"></script>
	{ezscript( $dependency_js_list )}
	{literal}
	<script type="text/javascript" >
	tinyMCE.init({
	        mode : "textareas",
	        theme : "simple"   //(n.b. no trailing comma, this will be critical as you experiment later)
	});
	</script>
	{/literal}
	
	<div class="block">
		<label>Content structure (as an unordered, nested list):</label>
		<textarea name="contentStructure" cols="100" rows="20"></textarea>
	</div>
	
	<input type="submit" name="ImportStructure" value="Import" class="button" />

</form>