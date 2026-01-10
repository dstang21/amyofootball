# AmyoFootball - Architecture Blueprint

## Overview
This document describes the target architecture for the modernized AmyoFootball platform, designed to support unlimited growth while maintaining code quality and developer productivity.

---

## Architecture Principles

### 1. **Separation of Concerns**
Each layer has a single, well-defined responsibility:
- **Controllers**: Handle HTTP requests/responses
- **Services**: Contain business logic
- **Repositories**: Abstract data access
- **Models**: Represent data entities
- **Views**: Handle presentation

### 2. **Dependency Injection**
All dependencies are injected, making code testable and maintainable:
```php
class PlayerController {
    public function __construct(
        private PlayerService $playerService,
        private CacheService $cache
    ) {}
}
```

### 3. **Interface-Based Design**
Program to interfaces, not implementations:
```php
interface PlayerRepositoryInterface {
    public function find(int $id): ?Player;
    public function findAll(): array;
}
```

### 4. **Configuration over Code**
All settings externalized to configuration files:
```php
// config/database.php
return [
    'driver' => env('DB_DRIVER', 'mysql'),
    'host' => env('DB_HOST', 'localhost'),
    'database' => env('DB_NAME'),
    // ...
];
```

### 5. **Security by Default**
Every input validated, every output escaped, every action authorized:
```php
$input = $validator->validate($request->all(), [
    'player_name' => 'required|string|max:255',
    'team_id' => 'required|integer|exists:teams,id'
]);
```

---

## System Architecture

### High-Level Overview
```
┌─────────────────────────────────────────────────────────────┐
│                         Users / Clients                      │
└─────────────────┬───────────────────────────────────────────┘
                  │
         ┌────────┴────────┐
         │                 │
    ┌────▼────┐      ┌────▼────┐
    │   Web   │      │   API   │
    │ Browser │      │ Clients │
    └────┬────┘      └────┬────┘
         │                │
         └────────┬───────┘
                  │
         ┌────────▼────────┐
         │   Load Balancer  │
         │   (Nginx/CDN)    │
         └────────┬────────┘
                  │
         ┌────────▼────────────────────┐
         │   Application Servers (x2)   │
         │  ┌─────────────────────┐    │
         │  │  PHP-FPM Workers    │    │
         │  │  - Controllers      │    │
         │  │  - Services         │    │
         │  │  - Repositories     │    │
         │  └──────┬──────────────┘    │
         └─────────┼───────────────────┘
                   │
      ┌────────────┼────────────┐
      │            │            │
┌─────▼─────┐ ┌───▼────┐ ┌────▼─────┐
│  Database │ │ Redis  │ │  Queue   │
│  (Master) │ │ Cache  │ │ Worker   │
└─────┬─────┘ └────────┘ └────┬─────┘
      │                        │
┌─────▼─────┐            ┌────▼─────┐
│ Database  │            │ External │
│ (Replica) │            │   APIs   │
└───────────┘            │(Sleeper) │
                         └──────────┘
```

---

## Application Layers

### 1. Presentation Layer (Views)

**Responsibility**: Display data to users

**Components**:
- PHP templates (initial phase)
- Vue.js components (admin panel)
- CSS/JavaScript assets

**Example Structure**:
```
src/Views/
├── layouts/
│   ├── app.php           # Main layout
│   ├── admin.php         # Admin layout
│   └── guest.php         # Login layout
├── pages/
│   ├── home.php
│   ├── players/
│   │   ├── index.php
│   │   ├── show.php
│   │   └── create.php
│   └── admin/
│       └── dashboard.php
└── components/
    ├── header.php
    ├── footer.php
    ├── player-card.php
    └── stats-table.php
```

---

### 2. Controller Layer

**Responsibility**: Handle HTTP requests, coordinate services, return responses

**Characteristics**:
- Thin controllers (no business logic)
- Input validation
- Response formatting
- Error handling

**Example**:
```php
namespace AmyoFootball\Controllers;

class PlayerController extends BaseController
{
    public function __construct(
        private PlayerService $playerService,
        private Validator $validator
    ) {}
    
    public function index(Request $request): Response
    {
        // Validate input
        $filters = $this->validator->validate($request->query(), [
            'position' => 'string|in:QB,RB,WR,TE',
            'team' => 'integer',
            'search' => 'string|max:100'
        ]);
        
        // Call service
        $players = $this->playerService->getPlayers($filters);
        
        // Return response
        return $this->view('players.index', [
            'players' => $players,
            'filters' => $filters
        ]);
    }
    
    public function show(int $id): Response
    {
        $player = $this->playerService->getPlayerById($id);
        
        if (!$player) {
            return $this->notFound('Player not found');
        }
        
        return $this->view('players.show', [
            'player' => $player,
            'stats' => $this->playerService->getPlayerStats($id),
            'rankings' => $this->playerService->getPlayerRankings($id)
        ]);
    }
}
```

---

### 3. Service Layer

**Responsibility**: Business logic, orchestrate operations, transaction management

**Characteristics**:
- Contains all business rules
- Coordinates multiple repositories
- Handles complex operations
- Transaction management

**Example**:
```php
namespace AmyoFootball\Services;

class PlayerService
{
    public function __construct(
        private PlayerRepository $playerRepo,
        private StatsRepository $statsRepo,
        private RankingRepository $rankingRepo,
        private CacheService $cache,
        private Logger $logger
    ) {}
    
    public function getPlayers(array $filters = []): array
    {
        $cacheKey = 'players:' . md5(serialize($filters));
        
        return $this->cache->remember($cacheKey, 3600, function() use ($filters) {
            return $this->playerRepo->findByFilters($filters);
        });
    }
    
    public function createPlayer(array $data): Player
    {
        DB::beginTransaction();
        try {
            // Validate business rules
            if ($this->playerRepo->existsByName($data['name'])) {
                throw new DuplicatePlayerException('Player already exists');
            }
            
            // Create player
            $player = $this->playerRepo->create($data);
            
            // Create initial stats if provided
            if (isset($data['stats'])) {
                $this->statsRepo->createForPlayer($player->id, $data['stats']);
            }
            
            // Clear cache
            $this->cache->tags(['players'])->flush();
            
            // Log action
            $this->logger->info('Player created', ['player_id' => $player->id]);
            
            DB::commit();
            return $player;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->logger->error('Failed to create player', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }
    
    public function syncFromSleeper(string $playerId): Player
    {
        // Complex business logic
        $sleeperData = $this->sleeperService->getPlayer($playerId);
        
        $player = $this->playerRepo->findBySourceId('sleeper', $playerId);
        
        if ($player) {
            return $this->updatePlayerFromSleeper($player, $sleeperData);
        } else {
            return $this->createPlayerFromSleeper($sleeperData);
        }
    }
}
```

---

### 4. Repository Layer

**Responsibility**: Data access abstraction, query building

**Characteristics**:
- Abstract database operations
- Return domain objects (Models)
- Implement query optimization
- Handle database-specific logic

**Example**:
```php
namespace AmyoFootball\Repositories;

class PlayerRepository extends BaseRepository
{
    protected string $table = 'players';
    protected string $model = Player::class;
    
    public function findByFilters(array $filters = []): array
    {
        $query = $this->db->table($this->table)
            ->select([
                'players.*',
                'teams.name as team_name',
                'teams.abbreviation as team_abbr'
            ])
            ->leftJoin('player_teams', 'players.id', '=', 'player_teams.player_id')
            ->leftJoin('teams', 'player_teams.team_id', '=', 'teams.id');
        
        if (isset($filters['position'])) {
            $query->join('player_teams', function($join) use ($filters) {
                $join->on('players.id', '=', 'player_teams.player_id')
                     ->where('player_teams.position', '=', $filters['position']);
            });
        }
        
        if (isset($filters['team'])) {
            $query->where('player_teams.team_id', '=', $filters['team']);
        }
        
        if (isset($filters['search'])) {
            $query->where('players.name', 'LIKE', '%' . $filters['search'] . '%');
        }
        
        return $query->get()->map(function($row) {
            return $this->hydrate($row);
        })->all();
    }
    
    public function getTopRanked(int $seasonId, int $limit = 10): array
    {
        return $this->db->table($this->table)
            ->select([
                'players.*',
                'draft_positions.ranking',
                'sources.name as source_name'
            ])
            ->join('draft_positions', 'players.id', '=', 'draft_positions.player_id')
            ->join('sources', 'draft_positions.source_id', '=', 'sources.id')
            ->where('draft_positions.season_id', '=', $seasonId)
            ->orderBy('draft_positions.ranking', 'ASC')
            ->limit($limit)
            ->get()
            ->map(fn($row) => $this->hydrate($row))
            ->all();
    }
    
    public function existsByName(string $name): bool
    {
        return $this->db->table($this->table)
            ->where('name', '=', $name)
            ->exists();
    }
}
```

---

### 5. Model Layer

**Responsibility**: Represent data entities, define relationships

**Characteristics**:
- Plain PHP objects (POPOs) or Active Record
- Define data structure
- Type safety
- Relationships
- Computed properties

**Example**:
```php
namespace AmyoFootball\Models;

class Player extends Model
{
    protected array $fillable = [
        'name',
        'first_name',
        'last_name',
        'position',
        'jersey_number',
        'height',
        'weight',
        'college',
        'birth_date',
        'sleeper_id',
        'espn_id'
    ];
    
    protected array $casts = [
        'jersey_number' => 'int',
        'height' => 'int',
        'weight' => 'int',
        'birth_date' => 'date'
    ];
    
    // Relationships
    public function team(): ?Team
    {
        return $this->belongsTo(Team::class, 'player_teams', 'player_id', 'team_id');
    }
    
    public function stats(): array
    {
        return $this->hasMany(PlayerStat::class, 'player_id');
    }
    
    public function rankings(): array
    {
        return $this->hasMany(DraftPosition::class, 'player_id');
    }
    
    // Computed properties
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
    
    public function getAgeAttribute(): int
    {
        return $this->birth_date ? 
            $this->birth_date->diffInYears(now()) : 
            0;
    }
    
    // Business methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
    
    public function getRankingForSeason(int $seasonId): ?int
    {
        $ranking = $this->rankings()
            ->where('season_id', $seasonId)
            ->first();
            
        return $ranking?->ranking;
    }
}
```

---

## Supporting Infrastructure

### 1. Caching Strategy

**Multi-Layer Cache**:
```php
class CacheService
{
    // Layer 1: Application memory (request-scoped)
    private array $memory = [];
    
    // Layer 2: APCu (server-scoped)
    private $apcu;
    
    // Layer 3: Redis (cluster-scoped)
    private $redis;
    
    public function get(string $key, $default = null)
    {
        // Check memory first
        if (isset($this->memory[$key])) {
            return $this->memory[$key];
        }
        
        // Check APCu
        if ($value = apcu_fetch($key)) {
            $this->memory[$key] = $value;
            return $value;
        }
        
        // Check Redis
        if ($value = $this->redis->get($key)) {
            apcu_store($key, $value, 300);
            $this->memory[$key] = $value;
            return $value;
        }
        
        return $default;
    }
}
```

**Cache Keys Convention**:
```
players:all                    # All players
players:123                    # Single player
players:position:QB            # Players by position
players:team:5                 # Players by team
stats:player:123:season:2024   # Player stats
rankings:season:2024:source:1  # Rankings
```

---

### 2. Queue System

**Async Job Processing**:
```php
// Dispatch job to queue
Queue::push(new SyncSleeperLeagueJob($leagueId));

// Job class
class SyncSleeperLeagueJob implements JobInterface
{
    public function __construct(
        private string $leagueId
    ) {}
    
    public function handle(SleeperService $sleeper): void
    {
        $sleeper->syncLeague($this->leagueId);
    }
    
    public function onFailure(\Exception $e): void
    {
        Log::error('League sync failed', [
            'league' => $this->leagueId,
            'error' => $e->getMessage()
        ]);
        
        // Retry with exponential backoff
        if ($this->attempts() < 3) {
            $this->release(60 * pow(2, $this->attempts()));
        }
    }
}
```

---

### 3. Event System

**Decouple Components**:
```php
// Fire event
Event::dispatch(new PlayerCreated($player));

// Listen to event
class UpdatePlayerCache
{
    public function handle(PlayerCreated $event): void
    {
        Cache::tags(['players'])->flush();
    }
}

class NotifySlackChannel
{
    public function handle(PlayerCreated $event): void
    {
        Slack::notify("New player added: {$event->player->name}");
    }
}
```

---

### 4. API Design

**RESTful Endpoints**:
```
GET    /api/v1/players              # List players
GET    /api/v1/players/{id}         # Get player
POST   /api/v1/players              # Create player
PUT    /api/v1/players/{id}         # Update player
DELETE /api/v1/players/{id}         # Delete player

GET    /api/v1/players/{id}/stats   # Get player stats
GET    /api/v1/players/{id}/rankings # Get player rankings

GET    /api/v1/teams                # List teams
GET    /api/v1/teams/{id}/players   # Get team roster

GET    /api/v1/leagues              # List leagues
GET    /api/v1/leagues/{id}/rosters # Get league rosters
```

**Response Format**:
```json
{
    "success": true,
    "data": {
        "id": 123,
        "name": "Patrick Mahomes",
        "position": "QB",
        "team": {
            "id": 15,
            "name": "Kansas City Chiefs",
            "abbreviation": "KC"
        }
    },
    "meta": {
        "version": "1.0",
        "timestamp": "2026-01-10T12:00:00Z"
    }
}
```

**Error Format**:
```json
{
    "success": false,
    "error": {
        "code": "PLAYER_NOT_FOUND",
        "message": "Player with ID 999 not found",
        "details": null
    },
    "meta": {
        "version": "1.0",
        "timestamp": "2026-01-10T12:00:00Z"
    }
}
```

---

## Database Architecture

### Schema Design Principles

1. **Normalization**: 3NF for transactional data
2. **Denormalization**: Calculated fields for performance
3. **Indexing**: Every foreign key, query column
4. **Partitioning**: Large tables by season/date
5. **Archiving**: Move old data to archive tables

### Core Tables

```sql
-- Players (core entity)
CREATE TABLE players (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    position VARCHAR(10),
    jersey_number INT,
    height INT,
    weight INT,
    college VARCHAR(100),
    birth_date DATE,
    sleeper_id VARCHAR(50) UNIQUE,
    espn_id VARCHAR(50) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_position (position),
    INDEX idx_sleeper_id (sleeper_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Player Teams (many-to-many with history)
CREATE TABLE player_teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    team_id INT NOT NULL,
    season_id INT NOT NULL,
    position VARCHAR(10),
    jersey_number INT,
    start_date DATE,
    end_date DATE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    UNIQUE KEY unique_player_team_season (player_id, team_id, season_id),
    INDEX idx_player (player_id),
    INDEX idx_team (team_id),
    INDEX idx_season (season_id)
) ENGINE=InnoDB;

-- Player Stats (per week/season)
CREATE TABLE player_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    season_id INT NOT NULL,
    week INT,
    passing_yards INT DEFAULT 0,
    passing_tds INT DEFAULT 0,
    rushing_yards INT DEFAULT 0,
    rushing_tds INT DEFAULT 0,
    receiving_yards INT DEFAULT 0,
    receiving_tds INT DEFAULT 0,
    receptions INT DEFAULT 0,
    targets INT DEFAULT 0,
    fantasy_points_standard DECIMAL(10,2),
    fantasy_points_ppr DECIMAL(10,2),
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    UNIQUE KEY unique_player_season_week (player_id, season_id, week),
    INDEX idx_player_season (player_id, season_id),
    INDEX idx_fantasy_points (fantasy_points_ppr)
) ENGINE=InnoDB;
```

### Read Replicas

For high-traffic reads:
```php
class Database {
    private PDO $master;
    private array $replicas;
    
    public function select(string $query, array $bindings = []): array
    {
        // Use random replica for reads
        $replica = $this->replicas[array_rand($this->replicas)];
        return $this->executeQuery($replica, $query, $bindings);
    }
    
    public function insert(string $query, array $bindings = []): int
    {
        // Always use master for writes
        return $this->executeQuery($this->master, $query, $bindings);
    }
}
```

---

## Security Architecture

### Authentication Flow
```
1. User submits credentials
   ↓
2. Server validates credentials
   ↓
3. Generate JWT token or session
   ↓
4. Return token to client
   ↓
5. Client includes token in subsequent requests
   ↓
6. Middleware validates token
   ↓
7. Inject user into request
```

### Authorization Levels
```php
enum Permission: string {
    case VIEW_PLAYERS = 'players.view';
    case CREATE_PLAYERS = 'players.create';
    case EDIT_PLAYERS = 'players.edit';
    case DELETE_PLAYERS = 'players.delete';
    case MANAGE_LEAGUES = 'leagues.manage';
    case ADMIN_ACCESS = 'admin.access';
}

class User {
    public function can(Permission $permission): bool
    {
        return $this->role->hasPermission($permission);
    }
}
```

### Input Validation
```php
class PlayerValidator {
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'in:QB,RB,WR,TE,K,DEF'],
            'jersey_number' => ['nullable', 'integer', 'between:0,99'],
            'height' => ['nullable', 'integer', 'between:60,90'],
            'weight' => ['nullable', 'integer', 'between:150,400'],
            'birth_date' => ['nullable', 'date', 'before:today'],
        ];
    }
}
```

---

## Performance Optimization

### Query Optimization
```php
// Bad: N+1 query problem
$players = $playerRepo->findAll();
foreach ($players as $player) {
    echo $player->team->name; // Causes N queries!
}

// Good: Eager loading
$players = $playerRepo->findAllWithTeams();
foreach ($players as $player) {
    echo $player->team->name; // Already loaded!
}
```

### Database Indexing Strategy
```sql
-- Query: Get top ranked QBs for season
-- Add compound index
CREATE INDEX idx_rankings_season_position 
ON draft_positions(season_id, player_id, ranking);

-- Query: Search players by name
-- Full-text search index
CREATE FULLTEXT INDEX idx_player_name_fulltext 
ON players(name, first_name, last_name);

-- Query: Get player stats for season
-- Covering index (includes all queried columns)
CREATE INDEX idx_stats_covering
ON player_stats(player_id, season_id, week, fantasy_points_ppr);
```

### Response Caching
```php
// HTTP cache headers
Response::make($content)
    ->header('Cache-Control', 'public, max-age=3600')
    ->header('ETag', md5($content))
    ->header('Last-Modified', $lastModified);

// Conditional requests
if ($request->header('If-None-Match') === $etag) {
    return Response::make('', 304); // Not Modified
}
```

---

## Monitoring & Observability

### Logging Strategy
```php
// Structured logging
Log::info('Player created', [
    'player_id' => $player->id,
    'player_name' => $player->name,
    'user_id' => Auth::id(),
    'ip' => $request->ip()
]);

// Error logging with context
Log::error('Failed to sync Sleeper data', [
    'league_id' => $leagueId,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString()
]);
```

### Performance Monitoring
```php
// Track slow queries
DB::listen(function($query) {
    if ($query->time > 100) {
        Log::warning('Slow query detected', [
            'sql' => $query->sql,
            'time' => $query->time,
            'bindings' => $query->bindings
        ]);
    }
});

// Track API response times
Middleware::timing(function($request, $response, $time) {
    Log::debug('API request', [
        'method' => $request->method(),
        'url' => $request->url(),
        'time' => $time,
        'status' => $response->status()
    ]);
});
```

---

## Deployment Architecture

### Zero-Downtime Deployment
```
1. Deploy new version to inactive server pool
2. Run database migrations (non-breaking)
3. Run health checks on new version
4. Gradually shift traffic (10% → 50% → 100%)
5. Monitor error rates
6. If errors: instant rollback
7. If success: decommission old version
```

### Environment Configuration
```
Development:  Local Laragon/XAMPP
Staging:      Cloud staging environment (mirror of production)
Production:   Multi-server setup with load balancing
```

---

## Scalability Roadmap

### Current Capacity
- 100 concurrent users
- 10 requests/second
- 10K database records

### Target Capacity (Phase 1)
- 1,000 concurrent users
- 100 requests/second  
- 100K database records

### Future Capacity (Phase 2)
- 10,000 concurrent users
- 1,000 requests/second
- 1M+ database records
- Multi-region deployment

---

## Technology Stack

### Core
- **Language**: PHP 8.2+
- **Database**: MySQL 8.0+ or PostgreSQL 15+
- **Cache**: Redis 7.0+
- **Queue**: Redis or Beanstalkd
- **Web Server**: Nginx

### Frontend
- **CSS**: Tailwind CSS 3+
- **JavaScript**: Vue.js 3 or vanilla ES6+
- **Build**: Vite or Webpack
- **Icons**: Font Awesome or Heroicons

### Development
- **Testing**: PHPUnit
- **Code Quality**: PHPStan (level 8), PHP CodeSniffer (PSR-12)
- **Dependency Management**: Composer 2
- **Version Control**: Git
- **CI/CD**: GitHub Actions

### Monitoring
- **Logging**: Monolog
- **Error Tracking**: Sentry
- **APM**: New Relic or DataDog (optional)
- **Uptime**: UptimeRobot

---

## Conclusion

This architecture provides:
- ✅ **Separation of concerns** - Easy to maintain
- ✅ **Testability** - Every component can be tested
- ✅ **Scalability** - Horizontal and vertical scaling
- ✅ **Performance** - Multi-layer caching, optimized queries
- ✅ **Security** - Input validation, authorization, audit logs
- ✅ **Observability** - Comprehensive logging and monitoring
- ✅ **Developer experience** - Clear structure, documented patterns

Ready to build a world-class application! 🚀
