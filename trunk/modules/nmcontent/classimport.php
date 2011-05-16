<?php

$tpl = eZTemplate::factory();

if(isset($_FILES['uploadedfile']))
{
	$dataOriginal = file_get_contents($_FILES['uploadedfile']['tmp_name']);
	
	$data = json_decode($dataOriginal, true);
	
	$class = new nmContentClass;
	$class->updateClasses($data, false);
	
	$tpl->setVariable( 'log', $class->getStatus() );
	$tpl->setVariable( 'data', $dataOriginal );
	
	return array( 'content' => $tpl->fetch( 'design:contentclass/import/step_2.tpl' ) );
}
elseif(isset($_POST['Import']))
{
	$data = json_decode($_POST['data'], true);
	
	$class = new nmContentClass;
	$class->updateClasses($data);
	
	return array( 'content' => $tpl->fetch( 'design:contentclass/import/step_3.tpl' ) );
}
else
{	
	return array( 'content' => $tpl->fetch( 'design:contentclass/import/step_1.tpl' ) );
}



?>