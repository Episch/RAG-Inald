# Backend

## Cronjob

```* * * * * cd /path/to/project && php bin/console messenger:consume async --time-limit=300 --memory-limit=128M --env=prod > /dev/null 2>&1```

## Ping
```curl http://localhost:11434/api/version```
```curl http://localhost:7474```
```curl http://localhost:9998/version```
