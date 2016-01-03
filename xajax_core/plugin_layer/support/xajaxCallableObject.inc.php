<?php
/*
	File: xajaxCallableObject.inc.php

	Contains the xajaxCallableObject class

	Title: xajaxCallableObject class

	Please see <copyright.inc.php> for a detailed description, copyright
	and license information.
*/

/*
	@package xajax
	@version $Id: xajaxCallableObject.inc.php 362 2007-05-29 15:32:24Z calltoconstruct $
	@copyright Copyright (c) 2005-2007 by Jared White & J. Max Wilson
	@copyright Copyright (c) 2008-2010 by Joseph Woolley, Steffen Konerow, Jared White  & J. Max Wilson
	@license http://www.xajaxproject.org/bsd_license.txt BSD License
*/

/*
	Class: xajaxCallableObject
	
	A class that stores a reference to an object whose methods can be called from
	the client via a xajax request.  <xajax> will call 
	<xajaxCallableObject->generateClientScript> so that stub functions can be 
	generated and sent to the browser.
*/
final class xajaxCallableObject
{
	/*
		Object: obj
		
		A reference to the callable object.
	*/
	private $callableObject;

	/*
		Object: reflectionClass
		
		The reflection class of the callable object.
	*/
	private $reflectionClass;
	
	/*
		Array: aExcludedMethods
		
		An associative array that will contain methods the library must not
		export to javascript code.
	*/
	private $aExcludedMethods;
	
	/*
		String: classpath
		
		The path to the file where the callable object class is defined.
	*/
	private $classpath = '';
	
	/*
		Array: aConfiguration
		
		An associative array that will contain configuration options for zero
		or more of the objects methods.  These configuration options will 
		define the call options for each request.  The call options will be
		passed to the client browser when the function stubs are generated.
	*/
	private $aConfiguration;
	
	/*
		Function: xajaxCallableObject
		
		Constructs and initializes the <xajaxCallableObject>
		
		obj - (object):  The object to reference.
	*/
	public function __construct($obj)
	{
		$this->callableObject = $obj;
		$this->reflectionClass = new \ReflectionClass(get_class($this->callableObject));
		$this->aConfiguration = array();
		$this->aExcludedMethods = array();
	}

	/*
		Function: getClassName
		
		Returns the class name of this callable object, without the namespace if any.
	*/
	private function getClassName()
	{
		// Get the class name without the namespace.
		return $this->reflectionClass->getShortName();
	}

	/*
		Function: getName
		
		Returns the name of this callable object.  This is typically the
		class name of the object.
	*/
	public function getName()
	{
		// Get the class name with the namespace.
		return $this->reflectionClass->getName();
	}


	public function getMethods()
	{
		$aReturn = array();
		foreach ($this->reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $xMethod)
		{
			$aReturn[] = $xMethod->getShortName();
		}
		return $aReturn;
	}

	/*
		Function: configure
		
		Used to set configuration options / call options for each method.
		
		sMethod - (string):  The name of the method.
		sName - (string):  The name of the configuration option.
		sValue - (string):  The value to be set.
	*/
	public function configure($sMethod, $sName, $sValue)
	{
		// Set the classpath
		if($sName == 'classpath')
		{
			if($sValue != '')
				$this->classpath = $sValue . '.';
			return;
		}
		// Set the excluded methods
		if($sName == 'excluded')
		{
			if(is_array($sValue))
				$this->aExcludedMethods = $sValue;
			return;
		}
		$sMethod = strtolower($sMethod);
		
		if (!isset($this->aConfiguration[$sMethod]))
			$this->aConfiguration[$sMethod] = array();
			
		$this->aConfiguration[$sMethod][$sName] = $sValue;
	}

	/*
		Function: generateRequests
		
		Produces an array of <xajaxRequest> objects, one for each method
		exposed by this callable object.
		
		sXajaxPrefix - (string):  The prefix to be prepended to the
			javascript function names; this will correspond to the name
			used for the function stubs that are generated by the
			<xajaxCallableObject->generateClientScript> call.
	*/
	public function generateRequests($sXajaxPrefix)
	{
		$aRequests = array();
		
		$sClass = $this->getClassName();
		
		foreach ($this->reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $xMethod)
		{
			$sMethodName = $xMethod->getShortName();
			// Exclude magic __call, __construct, __destruct methods
			if (2 < strlen($sMethodName) && "__" == substr($sMethodName, 0, 2))
				continue;
			// Exclude constructor and excluded methods
			if ($sClass == $sMethodName || in_array($sMethodName, $this->aExcludedMethods))
				continue;
			$aRequests[strtolower($sMethodName)] = 
				new xajaxRequest("{$sXajaxPrefix}{$this->classpath}{$sClass}.{$sMethodName}");
		}

		return $aRequests;
	}
	
	/*
		Function: generateClientScript
		
		Called by <xajaxCallableObject->generateClientScript> while <xajax> is 
		generating the javascript to be sent to the browser.

		sXajaxPrefix - (string):  The prefix to be prepended to the
			javascript function names.
	*/	
	public function generateClientScript($sXajaxPrefix)
	{
		$sClass = $this->getClassName();

		// Add the classpath to the prefix
		$sXajaxPrefix .= $this->classpath;
		
		echo "{$sXajaxPrefix}{$sClass} = {};\n";
		
		foreach ($this->reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $xMethod)
		{
			$sMethodName = $xMethod->getShortName();
			// Exclude magic __call, __construct, __destruct methods
			if (2 < strlen($sMethodName) && "__" == substr($sMethodName, 0, 2))
				continue;
			// Exclude constructor and excluded methods
			if ($sClass == $sMethodName || in_array($sMethodName, $this->aExcludedMethods))
				continue;
			// Print Js code for this method
			echo "{$sXajaxPrefix}{$sClass}.{$sMethodName} = function() { ";
			echo "return xajax.request( ";
			echo "{ xjxcls: '{$this->classpath}{$sClass}', xjxmthd: '{$sMethodName}' }, ";
			echo "{ parameters: arguments";
			
			$sSeparator = ", ";
			if (isset($this->aConfiguration['*']))
				foreach ($this->aConfiguration['*'] as $sKey => $sValue)
					echo "{$sSeparator}{$sKey}: {$sValue}";
			if (isset($this->aConfiguration[strtolower($sMethodName)]))
				foreach ($this->aConfiguration[strtolower($sMethodName)] as $sKey => $sValue)
					echo "{$sSeparator}{$sKey}: {$sValue}";

			echo " } ); ";
			echo "};\n";
		}
	}
	
	/*
		Function: isClass
		
		Determins if the specified class name matches the class name of the
		object referenced by <xajaxCallableObject->obj>.
		
		sClass - (string):  The name of the class to check.
		
		Returns:
		
		boolean - True of the specified class name matches the class of
			the object being referenced; false otherwise.
	*/
	public function isClass($sClass)
	{
		if ($this->getName() === $sClass)
			return true;
		return false;
	}
	
	/*
		Function: hasMethod
		
		Determines if the specified method name is one of the methods of the
		object referenced by <xajaxCallableObject->obj>.
		
		sMethod - (object):  The name of the method to check.
		
		Returns:
		
		boolean - True of the referenced object contains the specified method,
			false otherwise.
	*/
	public function hasMethod($sMethod)
	{
		return $this->reflectionClass->hasMethod($sMethod) || $this->reflectionClass->hasMethod('__call');
	}
	
	/*
		Function: call
		
		Call the specified method of the object being referenced using the specified
		array of arguments.
		
		sMethod - (string): The name of the method to call.
		aArgs - (array):  The arguments to pass to the method.
	*/
	public function call($sMethod, $aArgs)
	{
		if(!$this->hasMethod($sMethod))
			return;
		$objResponseManager = xajaxResponseManager::getInstance();
		$reflectionMethod = $this->reflectionClass->getMethod($sMethod);
		$objResponseManager->append($reflectionMethod->invokeArgs($this->callableObject, $aArgs));
	}
}
