<?php 

class nmContentClass
{
	var $msgList;
	
	function __construct()
	{
		$this->msgList = array();
	}
		
	function addAttributeStatus($class, $name, $inInstallation, $inSpec, $actions = array())
	{
		$this->msgList[$class]['attribute_list'][] = array(	'name' 				=> $name, 
															'in_installation'	=> $inInstallation, 
															'in_spec'			=> $inSpec, 
															'actions'			=> $actions);
	}
	
	function addClassStatus($class, $name, $exists)
	{
		$this->msgList[$class]['head'] = array(	'name' 		=> $name, 
												'exists'	=> $exists);
	}
	
	function getStatus()
	{
		// for each class
		foreach($this->msgList as $classKey => $class)
		{
			$classHasActions = false;
			
			// for each class attribute
			foreach($class['attribute_list'] as $attribute)
			{
				if(count($attribute['actions']) > 0)
				{
					$classHasActions = true;
				}
			}
			
			$this->msgList[$classKey]['has_actions'] = $classHasActions;
		}
		
		return $this->msgList;
	}
	
	function updateClasses($data, $commit=true)
	{
		$log = array();
		
		// for each class
		foreach($data['class_list'] as $k => $classData)
		{
			$log[$k]['data'] = $classData;
			
			// check if the class already exists
			$classObject = eZContentClass::fetchByIdentifier($classData['class_identifier']);
			
			// if the class exists
			if($classObject)
			{
				$this->addClassStatus(	$classData['class_identifier'], 
										$classData['class_name'], 
										true);
				
				// get class id
				$classID = $classObject->attribute( 'id' );
				
				// get class version
				$classVersionID = $classObject->attribute('version');
				
				// get the language of current class which is always available
				$lang 			= $classObject->alwaysAvailableLanguage();
				$languageLocale = $lang->Locale;
				
				// fetch class attributes
				$attributeList = $classObject->fetchAttributes();
				
				// for each attribute
				$placement = 0;
				foreach($attributeList as $attribute)
				{
					$placement++;
					$attrInSpec = false;
					
					// get attribute identifier
					$attrIdentifier = $attribute->attribute('identifier');
					
					// look for the same attribute in our list
					$actions = array();
					foreach($classData['attribute_list'] as $attrKey => $attrData)
					{
						// if we found a match
						if($attrData['identifier'] == $attrIdentifier)
						{
							$attrInSpec = $attrData;
							
							// set attribute placement
			       			$attrData['placement'] = $placement; // $attrKey + 1; 
							
							// update attribute
							$actions = $this->updateAttribute($attribute, $attrData, $commit, $classVersionID);
			       			
							// remove the attribute from our spec
							unset($classData['attribute_list'][$attrKey]);
						}
					}
					
					if($attrInSpec != false)
					{
						$this->addAttributeStatus(	$classData['class_identifier'],
													$attrInSpec['name'],
													true,
													true, 
													$actions);
					}
					else
					{
						$this->addAttributeStatus(	$classData['class_identifier'],
													$attribute->attribute('name'),
													true,
													false, 
													$actions);
					}
				}
				
				// if there are any remaining attributes in our spec, meaning attributes that did not
				// already exist in the class
				if(count($classData['attribute_list']) > 0)
				{
					// for each remaining attribute
					foreach($classData['attribute_list'] as $attrKey => $attributeData)
				    {
				    	$placement++;
				    	
				    	$this->addAttributeStatus(	$classData['class_identifier'],
													$attributeData['name'],
													false,
													true, 
													array('Attribute will be created.'));
				    	
				    	if($commit)
				    	{
					    	// create attribute
				            $attribute = $this->createAttribute($classID, $attributeData['datatype'], $languageLocale);
				            
					        // set attribute placement
					        $attributeData['placement'] = $placement;
					        
					        // update attribute
				           	$this->updateAttribute($attribute, $attributeData, true, $classVersionID);
				           	
				           	// initialize object attributes
				           	$attribute->initializeObjectAttributes();
					        
				           	// add attribute to list of attributes
					        $attributeList[] = $attribute;
				    	}
				    }
				  
				    if($commit)
				    {
				    	$classObject->store( $attributeList );
				    }
				}
			}
			else
			{
				$this->addClassStatus(	$classData['class_identifier'], 
										$classData['class_name'], 
										false);
				
				if($commit)
				{
					// content class params
					$classParams 							= array();
			      	$classParams[ 'name' ] 					= $classData['class_name'];
			       	$classParams[ 'identifier' ]			= $classData['class_identifier'];
			       	$classParams[ 'contentobject_name' ] 	= '<' . $classData['attribute_list'][0][identifier] .  '>';
			       	$classParams[ 'version' ] 				= 0;
			       	$classParams[ 'is_container' ] 			= 1;
			       
			       	// create content class
				    $class = eZContentClass::create( false, $classParams );
				    $class->store();
				    
				    // get class attributes
				    $classID 		= $class->attribute( 'id' );
				    $ClassVersion 	= $class->attribute( 'version' );
				    
				    // store class in group
				    $ingroup =& eZContentClassClassGroup::create( $classID, $ClassVersion, 1, 'Content' );
				    $ingroup->store();	
				}
				
			    // create content class attributes
			    foreach($classData['attribute_list'] as $attrKey => $attributeData)
			    {
			    	$this->addAttributeStatus(	$classData['class_identifier'],
												$attributeData['name'],
												false,
												true, 
												array('Attribute will be created.'));
			    	
			    	if($commit)
			    	{
			    		// create attribute
			            $attribute = $this->createAttribute($classID, $attributeData['datatype']);
			            
				        // set attribute placement
				        $attributeData['placement'] = $attrKey + 1;
				        
				        // update attribute
			           	$this->updateAttribute($attribute, $attributeData, true, $ClassVersion);
				        
			           	// add attribute to list of attributes
				        $attributes[] = $attribute;	
			    	}
			    }
			  
			    if($commit)
			    {
			    	$class->store( $attributes );
			    }
			}
		}
	}
	
	function createAttribute($classID, $dataType, $languageLocale = false)
	{
		$attribute = eZContentClassAttribute::create( $classID, $dataType, array(), $languageLocale);
  		$dt = $attribute->dataType();
        $dt->initializeClassAttribute( $attribute );
        $attribute->store();        
        return $attribute;
	}
	
	function updateAttribute($attribute, $attributeData, $commit=true, $classVersionID)
	{
		// set attribute parameters
	    $attrParams = array();
	    $attrParams['identifier'] 		= $attributeData['identifier'];
	    $attrParams['name'] 			= $attributeData['name'];
	    $attrParams['description'] 		= $attributeData['desc'];
	    $attrParams['is_searchable'] 	= 1;
	        
	    if(isset($attributeData['required']) and $attributeData['required'] == 1)
	    {
	     	$attrParams['is_required'] 	= 1;
	    }
	    else
	    {
	    	$attrParams['is_required'] 	= 0;
	    }
	        
	    $attrParams['placement'] 		= $attributeData['placement'];
	    $attrParams['version'] 			= $classVersionID; // 0;

	    $actions = array();
	    foreach($attrParams as $key => $val)
	    {
	    	if($val != $attribute->attribute($key))
	    	{
	    		$actions[] = $key . ' will be updated from ' . $attribute->attribute($key) . ' to ' . $val . '.';
	    		
	    		if($commit)
		    	{
		    		$attribute->setAttribute( $key, $val);
		    	}
	    	}
		}
	       
		if($commit)
		{
			$attribute->store();
		}

		return $actions;
	}

	function getList()
	{
		// prepare result
		$result = array();
	
		// fetch group list
		$groupList = eZContentClassGroup::fetchList();

		// for each content class group
		foreach( $groupList as $group )
		{
			$groupDetails = array(	'id' 	=> $group->attribute( 'id' ), 
									'name' 	=> $group->attribute( 'name' ));
			
			// fetch classes within group
		    $classList = eZContentClassClassGroup::fetchClassList( null, $group->attribute( 'id' ) );
		    
		    // prepare class result list
		    $classResultList = array();
		    
		    // for each class
		    foreach( $classList as $class )
		    {
		    	$classDetails = array(	'id' 			=> $class->attribute( 'id' ), 
		    							'identifier'	=> $class->attribute( 'identifier' ), 
		    							'name'			=> $class->attribute( 'name' ));
		    
				// get class data map
		        $dataMap = $class->attribute( 'data_map' );
		        
		        // prepare attribute list
		        $attributeList = array();
		        
		        // for each attribute
		        foreach( $dataMap as $identifier => $attribute )
		        {
		        	$attributeDetails = array(	'id' 			=> $attribute->attribute( 'id' ), 
		        								'identifier'	=> $attribute->attribute( 'identifier' ), 
		        								'name'			=> $attribute->attribute( 'name' ), 
		        								'type'			=> $attribute->attribute( 'data_type_string' ), 
		        								'type_name'		=> $attribute->dataType()->Name, 
		        								'is_required'	=> $attribute->attribute( 'is_required' ));
		        	
		        	// add to attribute list
		        	$attributeList[] = $attributeDetails;
		        }
		        
		        $classResultList[] = array(	'details' 		=> $classDetails, 
		        							'attributes'	=> $attributeList);
		    }
		    
		    $result[] = array(	'details' 		=> $groupDetails, 
		    					'class_list'	=> $classResultList);
		}
		
		return $result;
	}
}

?>