# CODEBUDDY.md This file provides guidance to CodeBuddy when working with code in this repository.

## Common Commands

### Install Dependencies
```bash
composer install
```
This also runs `patch.php` via `post-autoload-dump` to apply any vendor patches from `patches/`.

### Generate XAML/C# Code
```bash
composer generate
# or: php src/Generate.php [winui]
```
Generates `generated/MainWindow.xaml`, `generated/MainWindow.xaml.cs`, and `generated/MainWindow.html` from the PHP UI definitions in `src/Ui/`. Always re-run this after modifying any UI code.

### Run Tests
```bash
vendor/bin/phpunit
```
Runs 36 tests across all backend services and the HTTP router. Tests use PHPUnit 11 with configuration in `phpunit.xml`. The test suite is at `tests/Backend/`.

### Run a Single Test
```bash
vendor/bin/phpunit --filter=TestClassName::testMethodName
```
Example: `vendor/bin/phpunit --filter=ConfigServiceTest::testGetAllReturnsDefaults`

### Start PHP Backend Server
```bash
composer serve
# or: php -S localhost:7891 src/Backend/Server.php
```
Starts the PHP built-in server on port 7891. The C# WPF client automatically starts this when launched.

### Build WPF Client
```bash
cd generated
dotnet build -c Release
dotnet run --project Wurrow.csproj
```
Requires .NET 8.0 SDK and WebView2 Runtime.

## Architecture

### High-Level Overview

Wurrow is a Windows system cleanup and optimization tool with a **PHP backend + WPF/C# desktop client** architecture. The project uses **perry-php**, a custom framework that generates WPF XAML and C# code-behind from PHP widget definitions — conceptually similar to SwiftUI's declarative approach but targeting Windows desktop.

The data flow is: **PHP Service → SSE/JSON API → C# StreamingClient/ApiClient → WPF UI**. The PHP backend handles all business logic (file scanning, registry reading, PowerShell execution), while the C# client is purely a presentation layer.

### Project Structure

```
src/
├── Backend/
│   ├── Router.php           # Lightweight HTTP router with SSE support
│   ├── Server.php           # API endpoint definitions (all routes in one file)
│   └── Services/            # 7 backend services (all final classes, no inheritance)
├── Ui/
│   ├── WurrowApp.php        # Root widget tree + code generation entry point
│   ├── Theme.php            # Color constants and style factory methods
│   ├── Tool.php             # PHP 8.1 enum: 6 tool definitions
│   ├── Views/               # 7 tab views (one per tool)
│   └── Components/          # Shared UI widgets (PillButton, TaskReport, ToolHero)
└── Generate.php             # CLI script that triggers code generation

cs/                          # Hand-written C# files (NOT generated)
├── App.xaml.cs              # App lifecycle: starts PHP server, exposes Api/Stream singletons
├── ApiClient.cs             # HttpClient wrapper for JSON endpoints
├── StreamingClient.cs       # SSE stream parser with event callbacks
├── MainWindow.Bridge.cs     # Partial class bridging WebView2 ↔ PHP, lazy-loading tabs
└── TreemapBridge.cs         # WebView2 bidirectional JS ↔ C# communication
```

### Key Design Patterns

**Router (Fluent + Facade)**: The `Router` class uses a fluent interface for route registration (`$router->get('/path', $handler)->post('/path', $handler)`) and provides static facade methods (`Router::json()`, `Router::sseSend()`, `Router::error()`) for response handling. URL matching is two-tier: exact match via hash table first, then regex for `{param}` patterns. CORS is restricted to empty or null origins only (same-origin or WebView2 file:// protocol).

**Service Architecture**: All 7 services are `final class` with no shared base class or interface. They share a consistent pattern: constructor injection of `PowerShellRunner`, a `cancel()` method with a `bool $cancelled` flag, and dual `preview()`/`execute()` methods that accept a `callable $emit` for SSE event streaming. This design prioritizes explicitness over abstraction.

**SSE Streaming**: Services push typed events (`line`, `progress`, `summary`, `done`) through an `$emit` callback. `Router::sseSend()` formats these as SSE (`event: ...\ndata: ...\n\n`) with `flush()`. The C# `StreamingClient` parses the HTTP response stream line-by-line, dispatches parsed events to C# callbacks, which use `Dispatcher.Invoke()` to update the WPF UI thread.

**Security Model**: User input is **never** interpolated into PowerShell script strings. Instead, `PowerShellRunner` passes user values as environment variables to the child process, and scripts read them via `$env:VAR_NAME`. This prevents command injection. `ConfigService` uses whitelist filtering (`array_intersect_key`) when saving configs, discarding unknown keys.

**Three-State View Pattern**: Every tool view (Clean, Purge, Installer, Optimize) follows an identical structure: Hero state (large Orb + title + action buttons), Running state (status bar + progress), Done state (summary banner). States are toggled via Perry `Binding` visibility flags (e.g., `cleanHeroVisible`, `cleanRunningVisible`, `cleanDoneVisible`).

**Perry-php UI Framework**: The UI is defined declaratively in PHP using widget classes (`VStack`, `HStack`, `Text`, `Button`, `TabView`, `ScrollView`, `WebView`, `Progress`, `Spacer`). `Binding` objects provide reactive state. `Action::custom()` embeds raw C# code directly in PHP — these are the API call implementations. `WurrowApp::generate()` walks the widget tree to produce XAML; `generateCodeBehind()` produces the corresponding C#.

**Cross-Platform Simulation**: All services check `PowerShellRunner::isWindows()`. On non-Windows systems, they return simulated/mock data, allowing development and testing without a Windows machine.

### Code Generation Flow

1. `php src/Generate.php` instantiates `WurrowApp`
2. `WurrowApp::createBindings()` creates ~30 shared `Binding` objects (state variables)
3. `WurrowApp::buildRoot()` constructs the full widget tree with 7 `TabView` items
4. `generate()` walks the tree → `generated/MainWindow.xaml`
5. `generateCodeBehind()` walks the tree → `generated/MainWindow.xaml.cs`
6. `getWebViewHtml()` extracts the treemap WebView2 HTML → `generated/MainWindow.html`

The `generated/` directory output is a standalone WPF project. Hand-written C# files in `cs/` (App.xaml.cs, ApiClient, StreamingClient, Bridge, TreemapBridge) are separate and NOT regenerated — they provide the runtime infrastructure.

### C# Client Infrastructure

`App.xaml.cs` manages the full lifecycle: on startup, it locates the project root (via `WURROW_ROOT` env var or walking up to find `composer.json`), starts `php -S localhost:7891` as a child process, polls `/api/health` until ready, and exposes `App.Api` (ApiClient singleton) and `App.Stream` (StreamingClient singleton). On exit, it terminates the PHP process. Port conflict detection uses `TcpClient` — if the port is already in use, it assumes another instance is running.

### Testing

Tests are in `tests/Backend/` and use PHPUnit 11. Service tests work by collecting SSE events into arrays and asserting on event types and data structures. `ConfigServiceTest` uses a temporary directory for isolated file I/O. Tests cover both Windows (PowerShell) and non-Windows (simulated) code paths. The test bootstrap is `vendor/autoload.php` (PSR-4 autoloading with namespace `Yangweijie\Wurrow\` mapped to `src/`).

### Key Dependencies

- **perry-php** (`yangweijie/perry-php:dev-main`): The UI code generation framework. Changes to perry-php may require vendor patches via `patches/`.
- **PHPUnit 11** (`require-dev`): Test framework.
- **PHP >= 8.2**: Required for enums, readonly classes, and other modern PHP features.
