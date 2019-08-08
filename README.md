# edu-mutex
Simple PHP mutex leveraging the database

Example usage
```php
$hasLock = FwkMutexBoDatabase::AcquireLock($lockName, (string)$lockValue, self::LOCK_TIME_TO_LIVE, self::ACQUIRE_LOCK_RETRY_ATTEMPTS);
// code
FwkMutexBoDatabase::ReleaseLock($lockName);
```