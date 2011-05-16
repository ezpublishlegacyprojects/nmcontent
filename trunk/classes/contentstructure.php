<?php

class nmContentStructure
{
	var $contentClassList;
	var $userObjectID;
	var $dummyContentList;
	
	function __construct()
	{
		$classList = eZContentClass::fetchList();
		
		foreach($classList as $class)
		{
			$name = $class->name();
			$this->contentClassList[$name] = $class->attribute('identifier');
		}
		
		// get the current user
		$user 				= eZUser::currentUser();
		$this->userObjectID = $user->attribute( 'contentobject_id' );
		
		$this->dummyContentList = false;
	}
	
	function setDummyContent($nodeID)
	{
		// set params
		$fetchParams 			= array();
		$fetchParams['Depth'] 	= 1;
		
		// fetch node list
		foreach(eZContentObjectTreeNode::subTreeByNodeID($fetchParams, $nodeID) as $node)
		{
			$class = $node->attribute('class_identifier');
			$this->dummyContentList[$class] = $node;
		}
	}
	
	function getDummyContent($classIdentifier)
	{
		if($this->dummyContentList and isset($this->dummyContentList[$classIdentifier]))
		{
			return $this->dummyContentList[$classIdentifier];
		}
		else
		{
			return false;
		}
	}
	
	function formatStructure($input)
	{
		$html = new simple_html_dom();
		
		// Load HTML from a string
		$html->load($input);
		
		$result = array();
		$this->walkUnorderedList( $html->find( "ul", 0 ), $result );
		return $result;
	}
	
	function walkUnorderedList( $ul, &$ar )
	{
	    foreach( $ul->children as $li )
	    {
	        if ( $li->tag != "li" )
	        {
	            continue;
	        }
	        
	        $children = array( );
	        foreach( $li->children as $ulul )
	        {
	            if ( $ulul->tag != "ul" )
	            {
	                continue;
	            }
	            $this->walkUnorderedList( $ulul, $children );
	        }
	        
	        // if the element has children
	        if(count($children) > 0)
	        {
	        	// format label
	        	$labelPieces 	= explode("\n", trim($li->plaintext));
	        	$label 			= $labelPieces[0];
	        }
	        else
	        {
	        	$label = $li->plaintext;
	        	$children = false;
	        }
	        
	        // split label into parts
	        $labelPieces 	= explode('(', $label);
	        $label 			= trim($labelPieces[0]);
	        $class 			= trim(str_replace(')', '', $labelPieces[1]));
	        
	        $item = array(	'label' 	=> $label, 
	        				'class'		=> $class);
	        				
	        if($children)
	        {
	        	$item['children'] = $children;
	        }
	        
	        $ar[] = $item;
	    }
	}
	
	function importStructure($parentNodeID, $structure)
	{
		foreach($structure as $item)
		{
			// get the current content class
			$contentClass = $this->getClass($item['class']);
			
			// if the content class does not exist
			if(!$contentClass)
			{
				eZDebug::writeError('Could not create ' . $item['label']. '. Could not identify the content class ' . $item['class'] . '.');
			}
			
			else
			{
				// fetch duplicate nodes
				$nodeList = $this->fetchDuplicates($contentClass->attribute('identifier'), $parentNodeID);
			
				$nodeExists = false;
				
				// for each node
				foreach($nodeList as $node)
				{
					// if there already exists a node with the same name
					if($node->attribute('name') == $item['label'])
					{
						$nodeExists = true;
						$newNodeID = $node->attribute('node_id');
						eZDebug::writeNotice('Could not create ' . $item['label']. ' under the node ' . $parentNodeID .  '. There already exists a node with the same name.');
					}
				}
				
				// provided that the node does not already exist
				if(!$nodeExists)
				{
					// get dummy content
					$dummyNode = $this->getDummyContent($contentClass->attribute('identifier'));
				
					// if dummy content exists
					if($dummyNode)
					{
						// copy dummy content
						$object = $this->copyDummyContent($dummyNode, $parentNodeID);
						
						// update name of the new content
						$this->updateName($object, $item['label']);
					}
					else
					{
						// create the node
						$contentObject = $this->createNode(	$contentClass,
															$parentNodeID, 
															$item['label']);
						
						$newNodeID = $contentObject->attribute('main_node_id');
					}
				}
				
				// if the item has children
				if(isset($item['children']) and is_array($item['children']) and count($item['children']) > 0)
				{
					// repeat the process for the children
					$this->importStructure($newNodeID, $item['children']);
				}
			}
		}
	}
	
	function updateName($contentObject, $name)
	{
		$attributeIdentifier = $this->getTitleAttributeIdentifier($contentObject->attribute('class_identifier'));	
		
		$attributeList = array( $attributeIdentifier  => $name);
		$params = array();
		$params['attributes'] = $attributeList;
		
		$result = eZContentFunctions::updateAndPublishObject( $contentObject, $params );
	}
	
	function getTitleAttributeIdentifier($classIdentifier)
	{
		// TODO: here we assign the label to the first attribute. this is, of course
		// not always correct, and would be better handled by investigating the object
		// name pattern, but it's close enough for now
		$contentClass 			= $this->getClass($classIdentifier);
		$dataMap 				= $contentClass->dataMap();
		$attribute 				= array_shift($dataMap);
		return $attribute->attribute('identifier');
	}
	
	function createNode($contentClass, $parentNodeID, $name)
	{
		// setting general node details
		$params = array();
		$params['class_identifier'] = $contentClass->attribute('identifier'); 
		$params['creator_id'] 		= $this->userObjectID;
		$params['parent_node_id'] 	= $parentNodeID;
	
		// get attribute identifier
		$attributeIdentifier = $this->getTitleAttributeIdentifier($contentClass->attribute('identifier'));
	
		//setting attribute values
		$attributesData = array ( ) ;
		$attributesData[$attributeIdentifier] = $name; 
		$params['attributes'] = $attributesData;
			 
		//publishing the content:
		return eZContentFunctions::createAndPublishObject( $params );
	}
	
	
	function copyDummyContent($dummyNode, $parentNodeID)
	{
		$allVersions = true;
		$newParentNode = eZContentObjectTreeNode::fetch( $parentNodeID );
	
		// fetch object for dummy content
		$object = eZContentObject::fetch($dummyNode->attribute('contentobject_id'));
		
		// code copied from content/copy view
		$db = eZDB::instance();
	    $db->begin();
	    $newObject = $object->copy( $allVersions );
	    
	    // We should reset section that will be updated in updateSectionID().
	    // If sectionID is 0 then the object has been newly created
	    $newObject->setAttribute( 'section_id', 0 );
	    $newObject->store();
	
	    $curVersion        = $newObject->attribute( 'current_version' );
	    $curVersionObject  = $newObject->attribute( 'current' );
	    $newObjAssignments = $curVersionObject->attribute( 'node_assignments' );
	    unset( $curVersionObject );
	
	    // remove old node assignments
	    foreach( $newObjAssignments as $assignment )
	    {
	        $assignment->purge();
	    }
	
	    // and create a new one
	    $nodeAssignment = eZNodeAssignment::create( array(
	                                                     'contentobject_id' => $newObject->attribute( 'id' ),
	                                                     'contentobject_version' => $curVersion,
	                                                     'parent_node' => $parentNodeID,
	                                                     'is_main' => 1
	                                                     ) );
	    $nodeAssignment->store();
	
	    // publish the newly created object
	    eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $newObject->attribute( 'id' ),
	                                                              'version'   => $curVersion ) );
	    // Update "is_invisible" attribute for the newly created node.
	    $newNode = $newObject->attribute( 'main_node' );
	    eZContentObjectTreeNode::updateNodeVisibility( $newNode, $newParentNode );
	
	    $db->commit();
		
		return $newObject;		
	}
	
	function getClass($class)
	{
		// get the class from the identifier
		$contentClass = eZContentClass::fetchByIdentifier($class);
		
		// if the content class does not exist
		if(!$contentClass)
		{
			// try getting it from the name instead
			if(isset($this->contentClassList[$class]))
			{
				$contentClass = eZContentClass::fetchByIdentifier($this->contentClassList[$class]);
			}
		}
		
		return $contentClass;
	}
	
	function fetchDuplicates($identifier, $parentNodeID)
	{
		// set params
		$fetchParams = array();
		$fetchParams['ClassFilterType']  	= 'include';
		$fetchParams['ClassFilterArray'] 	= array($identifier);
		$fetchParams['Depth'] 				= 1;
		
		// fetch node list
		return eZContentObjectTreeNode::subTreeByNodeID($fetchParams, $parentNodeID);
	}
}