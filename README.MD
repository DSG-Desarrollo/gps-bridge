# Always modify composer.json execute composer dump-autoload

/gps-bridge
│
|── docs/
│
├── config/
| ├── Wialon/
| │ └── WialonError.php
│ ├── app.php
│ ├── database.php
│ ├── Wialon.php
│ └── cempro.php
│
├── src/
│ ├── Core/
│ │ └── Database.php
│ │
│ ├── Repositories/
│ │ └── UnitRepository.php
| │ └── UserRepository.php
│ │
│ ├── Services/
│ │ ├── WialonService.php
│ │ ├── CemproService.php
│ │ └── RateLimiter.php
| │ └── WialonMapper.php
│ │
│ └── Jobs/
│ └── SyncLocationsJob.php
│
├── scripts/
│ └── run.php
│
├── logs/
├── .env
|── phpdoc.xml
├── composer.json
└── vendor/
