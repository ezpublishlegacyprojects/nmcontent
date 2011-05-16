<?php

$tpl 	= eZTemplate::factory();
$class 	= new nmContentStructure;

// if the user has opted to import the structure
if(isset($_POST['ImportStructure']))
{
	// if the container for dummy content is specified
	if($_POST['dummyParentNodeID'] > 0)
	{
		$class->setDummyContent($_POST['dummyParentNodeID']);
	}

	// format structure
	$structure = $class->formatStructure($_POST['contentStructure']);

	// import structure
	$class->importStructure($_POST['parentNodeID'], $structure);
	
	$tpl->setVariable( 'parent_node_id', $_POST['parentNodeID'] );
	
	return array( 'content' => $tpl->fetch( 'design:contentclass/structure/step_2.tpl' ) );
}
else
{
	return array( 'content' => $tpl->fetch( 'design:contentclass/structure/step_1.tpl' ) );
}