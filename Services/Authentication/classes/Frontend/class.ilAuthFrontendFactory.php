<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Factory for auth frontend classes.
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de> 
 *
 */
class ilAuthFrontendFactory
{
	const CONTEXT_UNDEFINED = 0;
	
	// authentication with id and password. Used for standard form based authentication
	// soap auth (login) but not for (CLI (cron)?) and HTTP basic authentication
	const CONTEXT_STANDARD_FORM = 2;
	
	
	private $context = self::CONTEXT_UNDEFINED;
	private $credentials = null;
	private $logger = null;
	
	
	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->logger = ilLoggerFactory::getLogger('auth');
	}
	
	/**
	 * 
	 * @return \ilLogger
	 */
	public function getLogger()
	{
		return $this->logger;
	}
	
	/**
	 * Set context for following authentication requests
	 * @param int $a_context
	 */
	public function setContext($a_context)
	{
		$this->context = $a_context;
	}
	
	/**
	 * Get context
	 * @return int
	 */
	public function getContext()
	{
		return $this->context;
	}
	
	/**
	 * @return \ilAuthFrontendInterface
	 */
	public function getFrontend(ilAuthSession $session, ilAuthCredentials $credentials, array $providers)
	{
		switch($this->getContext())
		{
			case self::CONTEXT_STANDARD_FORM:
				$this->getLogger()->debug('Init auth frontend with standard auth context');
				include_once './Services/Authentication/classes/Frontend/class.ilAuthStandardFormFrontend.php';
				$frontend = new ilAuthStandardFormFrontend(
					$session, 
					$credentials,
					$providers
				);
				return $frontend;
			
			case self::CONTEXT_UNDEFINED:
				$this->getLogger()->error('Trying to init auth with empty context');
				break;
		}
	}
	
}
?>