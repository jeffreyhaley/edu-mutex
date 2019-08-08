<?php
class FwkMutexBoDatabase
{
	const UNLOCKED = 0;
    const LOCKED = 1;

	/**
	 * Attempts to create a lock
	 *
	 * @param string $name
	 *        	of the lock to be acquired.
	 * @param integer $time
	 *        	to wait for lock to become released.
     * @param integer $retry
     *          number of times to retry acquiring lock.  If not defined, use $time as $retry
	 * @return boolean acquiring result.
	 */
    public static function AcquireLock($lockName, $lockValue = null, $time = 600, $retry = null, $schema = 'dbo')
    {
        // Check the inputs
		FwkSecurityBoSanitize::ExceptionString($lockName);
        FwkSecurityBoSanitize::ExceptionString($lockValue, true);
		
		// Set constants
		$LOCKED = self::LOCKED;
        $UNLOCKED = self::UNLOCKED;
		
		// Clean up the old locks
		self::ClearOldLocks($schema);
		
		// If a value is not supplied use the PID
		if (empty($lockValue)) {
		    $lockValue = getmypid();
		}
		
        $DbPor = FwkDatabaseBo::Singleton();
		
        $SQL = <<<SQL
MERGE INTO [$schema].[Mutex] WITH (TABLOCK) AS [To]
USING (VALUES ( '$lockName',
      $LOCKED,
      $UNLOCKED,
      '$lockValue',
	  $time)) AS [From] ([MutexName], [MutexLocked], [MutexUnLocked], [MutexValue], [MutexTtl])
ON ( [From].[MutexName] = [To].[MutexName] )
WHEN NOT MATCHED
THEN
  INSERT ( [MutexName],
           [MutexLocked],
           [MutexValue],
		   [MutexTtl] )
  VALUES ([From].[MutexName],
          [From].[MutexLocked],
          [From].[MutexValue],
		  [From].[MutexTtl])
WHEN MATCHED AND [To].[MutexLocked] = [From].[MutexUnLocked] THEN
  UPDATE SET [MutexLocked] = [From].[MutexLocked], [MutexValue] = [From].[MutexValue], [MutexTtl] = [From].[MutexTtl], [MutexCreateDate] = GETDATE()
OUTPUT [INSERTED].[MutexLocked];
SQL;

        // If a number of retries is not set, use the $time as the number of retries.
        // This is for backwards compatibility, but also allows for a hard stop on acquiring a lock
        if (is_null($retry)) {
            $retry = $time;
        }

		// Loop for the specified amount of time.
        for ($counter = 0; $counter <= $retry; $counter++) {
            $locked = (int) $DbPor->FetchFirstResult($SQL);
            if ($locked === 1) {
                FwkLogBoLog4Php::Logger()->info('Lock Acquired.  MutexName=' . $lockName . '; MutexLock=' . $locked . '; MutexValue=' . $lockValue);
                return true;
            }
			
            FwkLogBoLog4Php::Logger()->info('Waiting to Acquire Lock. MutexName=' . $lockName . '; MutexLock=' . $lockName . '; MutexValue=' . $lockValue);
			
			// Sleep to ensure we're not looping too quickly
			sleep(1);
        }
		
        FwkLogBoLog4Php::Logger()->warn('Failed to Acquired lock.');
        return false;
    }

	/**
	 * Checks the lock name and releases it.
	 *
	 * @throws Exception
	 * @param string $lockName
	 * @return boolean
	 */
	public static function ReleaseLock($lockName, $schema = 'dbo')
	{
	    FwkSecurityBoSanitize::ExceptionString($lockName);
		
	    $LOCKED = self::LOCKED;
	    $UNLOCKED = self::UNLOCKED;
		
	    $DbPor = FwkDatabaseBo::Singleton();
		
	    $SQL = <<<SQL
MERGE INTO [$schema].[Mutex] WITH (TABLOCK) AS [To]
USING (VALUES ( '$lockName',
      $LOCKED)) AS [From] ([MutexName], [MutexLocked])
ON ( [From].[MutexName] = [To].[MutexName]
     AND [From].[MutexLocked] = [To].[MutexLocked] )
WHEN MATCHED THEN
  UPDATE SET [MutexLocked] = $UNLOCKED
OUTPUT [INSERTED].[MutexLocked];
SQL;
		
	    $unlocked = (int) $DbPor->FetchFirstResult($SQL);
		
	    if ($unlocked !== 0) {
	        FwkLogBoLog4Php::Logger()->error('Failed Removing lock, either it does not exit or has already been removed. MutexName=' . $lockName . 'PID=' . getmypid());
	        throw new Exception('Failed Removing lock. MutexName=' . $lockName);
	    }
		
	    FwkLogBoLog4Php::Logger()->info('Lock removed. MutexName=' . $lockName . 'PID=' . getmypid());
	    return true;
	}

	/**
     *@TODO The comment does not match the functionality.  This does not release the lock
	 * Releases lock by given name.
	 *
	 * @throws ExceptionString
	 * @param string $name
	 *        	of the lock to be released.
	 * @return boolean release result.
	 */
	public static function FetchLock($lockName, $schema = 'dbo')
	{
	    FwkSecurityBoSanitize::ExceptionString($lockName);
		
	    $DbPor = FwkDatabaseBo::Singleton();
	    $builder= $DbPor->GetSqlFactory();

	    $select = $builder->Select($schema.'Mutex');
	    $select->Col('MutexLocked');
	    $select->Where($builder->Clause()->EQ('MutexName', ':MutexName'));
	    $locked = (int) $DbPor->GetFirstResult($select, array('MutexName'=>$lockName));

	    if ($locked === 1) {
	        return true;
	    } else {
	        return false;
	    }
	}

    /**
     * Fetch the lock value by given name
     * @param $lockName
     * @return string
     */
    public static function FetchLockValue($lockName, $schema = 'dbo')
    {
        FwkSecurityBoSanitize::ExceptionString($lockName);

        $DbPor = FwkDatabaseBo::Singleton();
        $builder= $DbPor->GetSqlFactory();

        $select = $builder->Select($schema.'Mutex')->Header(__METHOD__);
        $select->Col('MutexValue');
        $select->Where($builder->Clause()->EQ('MutexName', ':MutexName'));
        return $DbPor->GetFirstResult($select, array('MutexName'=>$lockName));
    }

	/**
	 * Removes the expired locks.
	 * Checks the lock from the CreatedDate+TTL against the current date
	 * @return boolean
	 */
	public static function ClearOldLocks($schema = 'dbo')
	{
	    $DbPor = FwkDatabaseBo::Singleton();

		// This requires the 'TABLOCK', so it cannot be put into SQL builder until this feature is implemented.
		$SQL = 'DELETE FROM ['.$schema.'].[Mutex] WITH (TABLOCK) WHERE Dateadd(ss, MutexTtl, MutexCreateDate) < Getdate();';

	    $result = $DbPor->Execute($SQL, [], [FwkDatabaseBoCore::RETURN_OPTION => FwkDatabaseBoCore::RETURN_AFFECTED]);

	    if ($result === false) {
	        FwkLogBoLog4Php::Logger()->error('There was an error cleaning up the old locks.');
	        return false;
	    }
		
	    FwkLogBoLog4Php::Logger()->info('Old Locks cleared.');
	    return true;
	}
}
