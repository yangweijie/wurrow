# Wurrow

**Windows system cleanup and optimization tool** — powered by [perry-php](https://github.com/yangweijie/perry-php).

Wurrow is a cross-platform UI application built with PHP (backend API) and WPF/C# (desktop client). It provides:

- **Clean** — Remove Windows temp files, browser caches, thumbnails, prefetch, and update cache
- **Purge** — Clean development artifacts (node_modules, vendor, __pycache__, build output, IDE configs, log files)
- **Installers** — Sweep orphaned installer files (.exe, .msi, .appx)
- **Optimize** — Flush DNS, free memory, clear Internet cache, trigger disk optimization
- **Analyze** — Visual disk usage analyzer with interactive squarified treemap (WebView2)
- **Software** — List, search, and uninstall Windows applications
- **Settings** — Configure cleanup targets and server port

## Project Structure

```
wurrow/
├── bin/                  # Shell scripts (generate, start-server)
├── cs/                   # Hand-written C# code (ApiClient, App, Bridge, StreamingClient, TreemapBridge)
├── generated/            # Auto-generated XAML + C# (run `php src/Generate.php`)
├── patches/              # Vendor patch overrides
├── src/
│   ├── Backend/
│   │   ├── Router.php        # Lightweight HTTP router
│   │   ├── Server.php        # PHP built-in server entrypoint
│   │   └── Services/         # Backend services (7 total)
│   ├── Ui/
│   │   ├── Theme.php         # Visual theme constants
│   │   ├── Tool.php          # Tool enum (6 tools)
│   │   ├── WurrowApp.php     # Main app definition
│   │   ├── Components/       # Shared UI components
│   │   └── Views/            # Tab views (7 total)
│   └── Generate.php         # XAML/C# code generator
├── tests/                # PHPUnit test suite (36 tests)
└── vendor/               # Composer dependencies
```

## Requirements

- PHP >= 8.2
- Composer
- .NET 8.0 SDK (for the WPF client)
- WebView2 Runtime (included in Windows 11 / recent Windows 10)

## Quick Start

### 1. Install PHP Dependencies

```bash
composer install
```

### 2. Generate XAML & C# Code

```bash
./bin/generate
# or: php src/Generate.php
```

This generates `generated/MainWindow.xaml`, `generated/MainWindow.xaml.cs`, and `generated/MainWindow.html`.

### 3. Start PHP Backend (for development/testing)

```bash
./bin/start-server
# or: php -S localhost:7891 src/Backend/Server.php
```

The server listens on `http://localhost:7891` and provides:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/health` | Health check |
| POST | `/api/clean/preview` | Clean preview (SSE) |
| POST | `/api/clean/execute` | Execute clean (SSE) |
| POST | `/api/clean/cancel` | Cancel clean |
| POST | `/api/purge/preview` | Purge preview (SSE) |
| POST | `/api/purge/execute` | Execute purge (SSE) |
| POST | `/api/installer/preview` | Installer preview (SSE) |
| POST | `/api/installer/execute` | Execute installer clean (SSE) |
| POST | `/api/optimize/preview` | Optimize preview (SSE) |
| POST | `/api/optimize/execute` | Execute optimize (SSE) |
| GET | `/api/analyze` | Disk analysis |
| DELETE | `/api/analyze/trash` | Move to recycle bin |
| GET | `/api/software/list` | List installed software |
| GET | `/api/software/search` | Search software |
| POST | `/api/software/uninstall` | Uninstall software |
| GET | `/api/settings` | Read settings |
| POST | `/api/settings` | Save settings |

### 4. Build & Run WPF Client

Open the project in Visual Studio or build with the .NET CLI:

```bash
cd generated
dotnet build -c Release
dotnet run --project Wurrow.csproj
```

The app will automatically start the PHP backend when launched.

## Testing

```bash
vendor/bin/phpunit
```

Runs 36 tests covering all backend services and the HTTP router.

## Architecture

```
PHP Backend (perry-php)
    │
    ├── HTTP Router ─── SSE Streaming API
    │
    └── Services
        ├── CleanService       (PowerShell file cleanup)
        ├── PurgeService       (Dev artifact scanning)
        ├── InstallerService   (Installer file scanning)
        ├── OptimizeService    (System optimization)
        ├── AnalyzeService     (Disk usage analysis)
        ├── SoftwareService    (Registry app enumeration)
        └── ConfigService      (JSON config persistence)
            │
C# Client (WPF)
    │
    ├── ApiClient
    ├── StreamingClient (SSE)
    ├── TreemapBridge (WebView2 ↔ C#)
    └── Generated UI (XAML + Code-behind)
```

## License

MIT
