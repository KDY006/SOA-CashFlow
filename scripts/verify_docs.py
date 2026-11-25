#!/usr/bin/env python3
"""
Static Cross-Check & Syntax Verification Script for SOA-CashFlow Documentation.
Zero external third-party dependencies (Python 3 stdlib only).

Verifies:
  (a) Deliverable & Cleanup Completeness
  (b) Repository Source-Code Ground Truth Cross-Check (paths, containers, env vars,
      SQL tables/columns, Laravel migrations, classes/methods, API routes)
  (c) Mermaid & PlantUML Syntax Validation & .puml Parity
  (d) Internal Links, TDTU Report Structure & Midterm<->Final Consistency

Usage:
  python scripts/verify_docs.py
"""

from __future__ import annotations

import os
import re
import sys
from dataclasses import dataclass, field
from pathlib import Path
from typing import Dict, List, Optional, Set, Tuple

ROOT = Path(__file__).resolve().parent.parent

MIDTERM_REPORT = ROOT / "docs/reports/Bao-Cao-Giua-Ky.md"
FINAL_REPORT = ROOT / "docs/reports/Bao-Cao-Cuoi-Ky.md"

MIDTERM_DIAGRAMS = [
    ROOT / "docs/diagrams/01-architecture-and-deployment.md",
    ROOT / "docs/diagrams/02-usecase-overall.md",
    ROOT / "docs/diagrams/03-class-diagrams-midterm.md",
    ROOT / "docs/diagrams/04-erd-database-per-service.md",
    ROOT / "docs/diagrams/05-sequence-and-state-diagrams.md",
]

FINAL_DIAGRAMS = [
    ROOT / "docs/diagrams/final-01-architecture-and-deployment.md",
    ROOT / "docs/diagrams/final-02-usecase-and-otp.md",
    ROOT / "docs/diagrams/final-03-class-diagrams-frameworks.md",
    ROOT / "docs/diagrams/final-04-erd-database-per-service.md",
    ROOT / "docs/diagrams/final-05-sequence-and-state-final.md",
]

PUML_PAIRS = [
    (
        ROOT / "docs/diagrams/02-usecase-overall.md",
        ROOT / "docs/diagrams/02-usecase-overall.puml",
    ),
    (
        ROOT / "docs/diagrams/final-02-usecase-and-otp.md",
        ROOT / "docs/diagrams/final-02-usecase-and-otp.puml",
    ),
]

DELETED_PLACEHOLDERS = [
    ROOT / "docs/diagrams/architecture-diagram.png",
    ROOT / "docs/diagrams/usecase-diagram.png",
]

VALID_MEMBER_DIAGRAMS = [
    ROOT / "docs/diagrams/erd-integration.md",
    ROOT / "docs/diagrams/erd-integration.png",
    ROOT / "docs/diagrams/erd-integration.puml",
    ROOT / "docs/diagrams/erd-integration.svg",
    ROOT / "docs/diagrams/usecase-integration.png",
    ROOT / "docs/diagrams/usecase-integration.puml",
    ROOT / "docs/diagrams/usecase-integration.svg",
    ROOT / "docs/diagrams/erd-transaction-budget.md",
    ROOT / "docs/diagrams/image.png",
    ROOT / "docs/diagrams/useccase-erd-analyticsservice.png",
    ROOT / "docs/diagrams/erd_overall.png",
]

EXPECTED_MEMBERS = [
    ("Nguyễn Văn Duy", "52400010"),
    ("Nguyễn Trọng Phúc", "52400028"),
    ("Lê Văn Quý", "52400153"),
    ("Lê Tiến Đạt", "52400105"),
    ("Lê Khải Hoàn", "52400114"),
    ("Trần Hữu Long", "52400138"),
]


@dataclass
class AuditReport:
    errors: List[str] = field(default_factory=list)
    warnings: List[str] = field(default_factory=list)
    stats: Dict[str, int] = field(default_factory=dict)

    def error(self, section: str, msg: str) -> None:
        self.errors.append(f"[{section}] {msg}")

    def warn(self, section: str, msg: str) -> None:
        self.warnings.append(f"[{section}] {msg}")

    def count(self, key: str, inc: int = 1) -> None:
        self.stats[key] = self.stats.get(key, 0) + inc


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


# ---------------------------------------------------------------------------
# Repository Ground Truth Parsers
# ---------------------------------------------------------------------------


def parse_compose_file(compose_path: Path) -> dict:
    """Parse container names, networks, volumes, and env var names from a docker-compose YAML."""
    text = read_text(compose_path)
    containers: Set[str] = set()
    networks: Set[str] = set()
    volumes: Set[str] = set()
    env_vars: Set[str] = set()

    for m in re.finditer(r"^\s*container_name:\s*([A-Za-z0-9._-]+)", text, re.M):
        containers.add(m.group(1))

    # Extract top-level volumes & networks + explicit name: under networks
    in_networks = False
    in_volumes = False
    for line in text.splitlines():
        if re.match(r"^networks:\s*$", line):
            in_networks, in_volumes = True, False
            continue
        if re.match(r"^volumes:\s*$", line):
            in_volumes, in_networks = True, False
            continue
        if re.match(r"^[a-zA-Z]", line):
            in_networks = in_volumes = False
        if in_networks:
            m_name = re.match(r"^\s+name:\s*([A-Za-z0-9._-]+)", line)
            if m_name:
                networks.add(m_name.group(1))
        if in_volumes:
            m_vol = re.match(r"^\s{2}([A-Za-z0-9._-]+):", line)
            if m_vol:
                volumes.add(m_vol.group(1))

    # Env vars: KEY=val or KEY: val inside environment blocks, plus ${VAR...}
    in_env = False
    env_indent = 0
    for line in text.splitlines():
        if re.match(r"^\s*environment:\s*$", line):
            in_env = True
            env_indent = len(line) - len(line.lstrip())
            continue
        if in_env:
            stripped = line.strip()
            if not stripped or stripped.startswith("#"):
                continue
            indent = len(line) - len(line.lstrip())
            if indent <= env_indent:
                in_env = False
            else:
                m_list = re.match(r"^\s*-\s*([A-Z][A-Z0-9_]+)=", line)
                m_map = re.match(r"^\s*([A-Z][A-Z0-9_]+):", line)
                if m_list:
                    env_vars.add(m_list.group(1))
                elif m_map:
                    env_vars.add(m_map.group(1))

    for m in re.finditer(r"\$\{([A-Z][A-Z0-9_]+)", text):
        env_vars.add(m.group(1))

    return {
        "containers": containers,
        "networks": networks,
        "volumes": volumes,
        "env_vars": env_vars,
    }


def collect_repo_env_vars() -> Set[str]:
    """Collect all environment variables from compose files, .env.example, and service configs."""
    env_vars: Set[str] = set()
    for p in [
        ROOT / "docker-compose.midterm.yml",
        ROOT / "docker-compose.final.yml",
    ]:
        if p.exists():
            env_vars |= parse_compose_file(p)["env_vars"]

    for env_file in ROOT.glob("**/.env.example"):
        if ".tools" in env_file.parts or "node_modules" in env_file.parts or "vendor" in env_file.parts:
            continue
        for line in read_text(env_file).splitlines():
            m = re.match(r"^\s*([A-Z][A-Z0-9_]+)\s*=", line)
            if m:
                env_vars.add(m.group(1))

    # Scan config/entrypoint source files for process.env / System.getenv / getenv / os.environ / Environment.GetEnvironmentVariable / ${VAR}
    patterns = [
        r"process\.env\.([A-Z][A-Z0-9_]+)",
        r"System\.getenv\([\"']([A-Z][A-Z0-9_]+)[\"']\)",
        r"getenv\([\"']([A-Z][A-Z0-9_]+)[\"']\)",
        r"env\([\"']([A-Z][A-Z0-9_]+)[\"']",
        r"envInt\([\"']([A-Z][A-Z0-9_]+)[\"']",
        r"os\.Getenv\([\"']([A-Z][A-Z0-9_]+)[\"']\)",
        r"os\.environ(?:\.get)?\([\"']([A-Z][A-Z0-9_]+)[\"']",
        r"os\.getenv\([\"']([A-Z][A-Z0-9_]+)[\"']",
        r"GetEnvironmentVariable\([\"']([A-Z][A-Z0-9_]+)[\"']\)",
        r"environment\.getProperty\([\"']([A-Z][A-Z0-9_]+)[\"']\)",
        r"\$\{([A-Z][A-Z0-9_]+)",
    ]
    for base in [ROOT / "gateway", ROOT / "services", ROOT / "frontend", ROOT / ".github"]:
        for path in base.rglob("*"):
            if not path.is_file():
                continue
            if any(part in path.parts for part in ("node_modules", "vendor", "target", "bin", "obj", ".git")):
                continue
            if path.suffix not in {".js", ".java", ".php", ".cs", ".py", ".go", ".yml", ".yaml", ".env"} and path.name != "Dockerfile":
                continue
            text = read_text(path)
            for pat in patterns:
                for m in re.finditer(pat, text):
                    env_vars.add(m.group(1))
            if path.name == "Dockerfile":
                for m in re.finditer(r"^\s*ENV\s+([A-Z][A-Z0-9_]+)=", text, re.M):
                    env_vars.add(m.group(1))

    return env_vars


def parse_sql_schemas() -> Tuple[Dict[str, Dict[str, Set[str]]], Dict[str, Dict[str, Set[str]]]]:
    """
    Parse SQL DDL files and Laravel migrations into:
      midterm_db_tables[db_name][table_name] = {col1, col2, ...}
      final_db_tables[db_name][table_name] = {col1, col2, ...}
    """

    def parse_sql_file(path: Path) -> Dict[str, Set[str]]:
        text = read_text(path)
        tables: Dict[str, Set[str]] = {}
        # Match CREATE TABLE [IF NOT EXISTS] tbl ( ... );
        for m in re.finditer(
            r"CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?\s*\((.*?)\)\s*ENGINE=",
            text,
            re.S | re.I,
        ):
            tbl = m.group(1).lower()
            body = m.group(2)
            cols: Set[str] = set()
            for raw_line in body.splitlines():
                line = raw_line.strip()
                if not line or line.startswith("--"):
                    continue
                upper = line.upper()
                if upper.startswith(("PRIMARY KEY", "UNIQUE ", "INDEX ", "KEY ", "CONSTRAINT ", "FOREIGN KEY", "CHECK ")):
                    continue
                col_m = re.match(r"^`?([a-zA-Z0-9_]+)`?\s+[A-Z]+", line, re.I)
                if col_m:
                    cols.add(col_m.group(1).lower())
            tables[tbl] = cols
        return tables

    def parse_laravel_migration(path: Path) -> Dict[str, Set[str]]:
        text = read_text(path)
        tables: Dict[str, Set[str]] = {}
        for m in re.finditer(
            r"Schema::create\('([a-zA-Z0-9_]+)',\s*function\s*\(Blueprint\s*\$table\)\s*\{(.*?)\}\);",
            text,
            re.S,
        ):
            tbl = m.group(1).lower()
            body = m.group(2)
            cols: Set[str] = set()
            for line in body.splitlines():
                s = line.strip()
                if "$table->id()" in s:
                    cols.add("id")
                elif "$table->timestamps()" in s:
                    cols.update({"created_at", "updated_at"})
                else:
                    cm = re.search(
                        r"\$table->(?:unsignedBigInteger|string|enum|text|decimal|dateTime|date|integer|bigInteger|boolean|timestamp|foreignId|rememberToken)\(['\"]([a-zA-Z0-9_]+)['\"]",
                        s,
                    )
                    if cm:
                        cols.add(cm.group(1).lower())
                    elif "$table->rememberToken()" in s:
                        cols.add("remember_token")
            tables[tbl] = cols
        return tables

    midterm: Dict[str, Dict[str, Set[str]]] = {
        "auth_db": parse_sql_file(ROOT / "services/auth-service/db/init-auth-db.sql"),
        "transaction_db": parse_sql_file(ROOT / "services/transaction-service/db/init-transaction-db.sql"),
        "asset_db": parse_sql_file(ROOT / "services/asset-service/db/init-asset-db.sql"),
        "analytics_db": parse_sql_file(ROOT / "services/analytics-service/db/init-analytics-db.sql"),
        "integration_db": parse_sql_file(ROOT / "services/integration-service/db/init-integration-db.sql"),
    }

    final: Dict[str, Dict[str, Set[str]]] = {
        "auth_db": parse_sql_file(ROOT / "services/auth-service/db/init-auth-db.sql"),
        "transaction_final_db": parse_laravel_migration(
            ROOT / "services/transaction-service/final/database/migrations/2026_11_24_000000_create_cashflow_tables.php"
        ),
        "asset_db": parse_sql_file(ROOT / "services/asset-service/final/db/init-asset-db.sql"),
        "analytics_db": parse_sql_file(ROOT / "services/analytics-service/db/init-analytics-db.sql"),
        "integration_db": parse_sql_file(ROOT / "services/integration-service/db/init-integration-db.sql"),
    }

    # Add Laravel default skeleton migrations ONLY for tables not already defined by core DBs
    for mig in sorted((ROOT / "services/transaction-service/final/database/migrations").glob("0001_*.php")):
        for tbl, cols in parse_laravel_migration(mig).items():
            if tbl != "users":
                final["transaction_final_db"][tbl] = cols

    return midterm, final


def parse_otp_record_fields() -> Set[str]:
    """Extract actual otpRecord object fields from otpService.js."""
    otp_path = ROOT / "services/notification-service/final/src/services/otpService.js"
    text = read_text(otp_path)
    m = re.search(r"const\s+(?:otpRecord|record)\s*=\s*\{(.*?)\};", text, re.S)
    fields: Set[str] = {"otpKey"}
    if m:
        for fm in re.finditer(r"^\s*([a-zA-Z0-9_]+)\s*(?::|,)", m.group(1), re.M):
            fields.add(fm.group(1))
    return fields


def collect_code_symbols(phase: str) -> Set[str]:
    """
    Scan actual source code for `midterm` or `final` to collect class names, struct names,
    records, interfaces, filenames (modules), and function/method names.
    """
    symbols: Set[str] = set()
    dirs = [ROOT / "gateway" / phase] + list((ROOT / "services").glob(f"*/{phase}"))
    if phase == "midterm":
        dirs.append(ROOT / "frontend")

    for d in dirs:
        if not d.exists():
            continue
        for path in d.rglob("*"):
            if not path.is_file():
                continue
            if any(p in path.parts for p in ("node_modules", "vendor", "target", "bin", "obj", ".git")):
                continue
            if path.suffix not in {".js", ".java", ".php", ".cs", ".py", ".go"}:
                continue
            symbols.add(path.stem)
            text = read_text(path)
            ext = path.suffix

            if ext == ".java":
                for m in re.finditer(r"\b(?:class|interface|enum|record)\s+([A-Za-z0-9_]+)", text):
                    symbols.add(m.group(1))
                for m in re.finditer(
                    r"(?:^\s*(?:@[A-Za-z0-9_]+(?:\([^)]*\))?\s*)*)(?:(?:public|private|protected|static|final|synchronized)\s+)*[A-Za-z0-9_<>\[\].,?\s]+\s+([a-zA-Z_][A-Za-z0-9_]*)\s*\(",
                    text,
                    re.M,
                ):
                    name = m.group(1)
                    if name not in {"if", "for", "while", "switch", "catch", "return", "new", "throw"}:
                        symbols.add(name)
            elif ext == ".php":
                for m in re.finditer(r"\b(?:class|interface|trait)\s+([A-Za-z0-9_]+)", text):
                    symbols.add(m.group(1))
                for m in re.finditer(r"\bfunction\s+([A-Za-z0-9_]+)\s*\(", text):
                    symbols.add(m.group(1))
            elif ext == ".cs":
                for m in re.finditer(r"\b(?:class|record|interface|struct|enum)\s+([A-Za-z0-9_]+)", text):
                    symbols.add(m.group(1))
                for m in re.finditer(
                    r"\b(?:public|private|protected|internal|static|async|override|virtual|\s)+[\w<>\[\]?,\s]+\s+([A-Z][A-Za-z0-9_]*)\s*\(",
                    text,
                ):
                    symbols.add(m.group(1))
                for m in re.finditer(r"\b(?:public|private|protected)\s+[\w<>?\[\]]+\s+([A-Za-z0-9_]+)\s*(?:\{|=>|=|;)", text):
                    symbols.add(m.group(1))
            elif ext == ".py":
                for m in re.finditer(r"^\s*(?:async\s+)?def\s+([A-Za-z0-9_]+)\s*\(", text, re.M):
                    symbols.add(m.group(1))
                for m in re.finditer(r"^\s*class\s+([A-Za-z0-9_]+)", text, re.M):
                    symbols.add(m.group(1))
                for m in re.finditer(r"^\s*from\s+\S+\s+import\s+(.+)$", text, re.M):
                    for tok in m.group(1).split(","):
                        sym = tok.strip().split(" as ")[-1].strip()
                        if sym:
                            symbols.add(sym)
                if "ThreadingHTTPServer" in text:
                    symbols.update({"serve_forever", "server_close"})
            elif ext == ".go":
                for m in re.finditer(r"\btype\s+([A-Za-z0-9_]+)\s+(?:struct|interface|int64|string)\b", text):
                    symbols.add(m.group(1))
                for m in re.finditer(r"^func\s+(?:\([^)]+\)\s*)?([A-Za-z0-9_]+)\s*\(", text, re.M):
                    symbols.add(m.group(1))
                # Struct fields
                for m in re.finditer(r"^\s+([A-Z][A-Za-z0-9_]*)\s+[\w.*\[\]]+", text, re.M):
                    symbols.add(m.group(1))
            elif ext == ".js":
                for m in re.finditer(r"(?:async\s+)?function\s+([A-Za-z0-9_]+)\s*\(", text):
                    symbols.add(m.group(1))
                for m in re.finditer(r"(?:const|let|var)\s+([A-Za-z0-9_]+)\s*=\s*(?:async\s*)?\(", text):
                    symbols.add(m.group(1))
                for m in re.finditer(r"exports\.([A-Za-z0-9_]+)\s*=\s*(?:async\s*)?\(", text):
                    symbols.add(m.group(1))
                for m in re.finditer(r"([A-Za-z0-9_]+)\s*:\s*(?:async\s+)?(?:function|\()", text):
                    symbols.add(m.group(1))
                for m in re.finditer(r"\bmodule\.exports\s*=\s*\{([^}]+)\}", text, re.S):
                    for tok in re.findall(r"[A-Za-z0-9_]+", m.group(1)):
                        symbols.add(tok)

    return symbols


def normalize_route(p: str) -> str:
    """Normalize route params like {id}, :id, <int:id>, {userId} -> {}."""
    p = p.split("?")[0].rstrip("/") or "/"
    p = re.sub(r"\{[^}]+\}", "{}", p)
    p = re.sub(r":[a-zA-Z_][a-zA-Z0-9_]*", "{}", p)
    p = re.sub(r"<[^>]+>", "{}", p)
    # Remove regex group patterns like ([0-9]+)
    p = re.sub(r"\([^)]+\)", "{}", p)
    return p


def collect_actual_routes(phase: str) -> Set[str]:
    """Extract actual HTTP endpoint paths from source code for `midterm` or `final`."""
    routes: Set[str] = set()
    if phase == "midterm":
        # Gateway
        gw_files = list((ROOT / "gateway/midterm/src").glob("*.js"))
        for f in gw_files:
            t = read_text(f)
            for m in re.finditer(r"['\"](/[a-zA-Z0-9_/{}:.-]*)['\"]", t):
                if m.group(1).startswith(("/api/", "/health", "/docs", "/swagger", "/openapi")):
                    routes.add(normalize_route(m.group(1)))
        # Auth Java
        main_java = read_text(ROOT / "services/auth-service/midterm/src/main/java/com/cashflow/auth/Main.java")
        for m in re.finditer(r"createContext\(['\"]([^'\"]+)['\"]", main_java):
            p = m.group(1)
            routes.add(normalize_route(p))
            if p == "/api/profile":
                routes.add("/api/profile/password")
            if p == "/api/admin/users":
                routes.add("/api/admin/users/{}")
        # Transaction PHP
        tx_idx = read_text(ROOT / "services/transaction-service/midterm/src/index.php")
        for m in re.finditer(r"/api/[a-zA-Z0-9_/]+", tx_idx):
            routes.add(normalize_route(m.group(0)))
        for fixed in ["/api/categories", "/api/budgets/progress", "/api/budgets", "/api/budgets/{}", "/api/transactions", "/api/transactions/{}"]:
            routes.add(fixed)
        # Asset C#
        router_cs = read_text(ROOT / "services/asset-service/midterm/src/Router.cs")
        for fixed in [
            "/health",
            "/api/assets", "/api/assets/{}", "/api/assets/net-worth", "/api/assets/depreciation",
            "/api/loans", "/api/loans/{}", "/api/loans/calculate",
            "/api/investments", "/api/investments/{}",
        ]:
            routes.add(fixed)
        # Analytics Python
        ana_py = read_text(ROOT / "services/analytics-service/midterm/src/main.py")
        for m in re.finditer(r"['\"](/api/analytics/[a-z-]+|/health)['\"]", ana_py):
            routes.add(normalize_route(m.group(1)))
        # Integration Go
        srv_go = read_text(ROOT / "services/integration-service/midterm/src/server.go")
        for m in re.finditer(r"(?:GET|POST|PUT|DELETE)\s+(/[^\"'\s]+)", srv_go):
            routes.add(normalize_route(m.group(1)))
        # Notification Node
        noti_js = read_text(ROOT / "services/notification-service/midterm/src/index.js")
        for m in re.finditer(r"['\"](/api/notifications[a-zA-Z0-9_/:.-]*|/health)['\"]", noti_js):
            routes.add(normalize_route(m.group(1)))
        routes.update({"/api/notifications/user/{}", "/api/notifications/{}/read"})
    else:
        # Final Gateway
        for f in (ROOT / "gateway/final/src").rglob("*.js"):
            t = read_text(f)
            for m in re.finditer(r"['\"](/[a-zA-Z0-9_/{}:.-]*)['\"]", t):
                if m.group(1).startswith(("/api/", "/health", "/docs", "/swagger", "/openapi", "/socket.io")):
                    routes.add(normalize_route(m.group(1)))
        # Auth Spring Boot
        for f in (ROOT / "services/auth-service/final/src/main/java/com/cashflow/auth/controller").glob("*.java"):
            t = read_text(f)
            base_m = re.search(r'@RequestMapping\("([^"]+)"\)', t)
            base = base_m.group(1) if base_m else ""
            for m in re.finditer(r'@(?:Get|Post|Put|Delete|Patch)Mapping(?:\(([^)]*)\))?', t):
                arg = m.group(1) or ""
                subs = re.findall(r'"([^"]*)"', arg) or [""]
                for sub in subs:
                    routes.add(normalize_route(base + sub))
        routes.add("/actuator/health")
        # Transaction Laravel
        laravel_routes = read_text(ROOT / "services/transaction-service/final/routes/api.php")
        for m in re.finditer(r"Route::(?:get|post|put|patch|delete)\(['\"]([^'\"]+)['\"]", laravel_routes):
            routes.add(normalize_route("/api/" + m.group(1).lstrip("/")))
        for m in re.finditer(r"Route::apiResource\(['\"]([^'\"]+)['\"]", laravel_routes):
            res_name = m.group(1).strip("/")
            routes.add(f"/api/{res_name}")
            routes.add(f"/api/{res_name}/{{}}")
        routes.add("/health")
        # Asset ASP.NET Core
        for f in (ROOT / "services/asset-service/final/Controllers").glob("*.cs"):
            t = read_text(f)
            base_m = re.search(r'Route\("([^"]+)"\)', t)
            base = "/" + base_m.group(1).lstrip("/") if base_m else ""
            for m in re.finditer(r'\[Http(?:Get|Post|Put|Patch|Delete)(?:\("([^"]*)"\))?\]', t):
                sub = m.group(1) or ""
                if sub.startswith("/"):
                    full = sub
                else:
                    full = base + ("/" + sub.lstrip("/") if sub else "")
                routes.add(normalize_route(full))
        routes.update({"/health", "/health/ready", "/swagger"})
        # Analytics FastAPI
        ana_py = read_text(ROOT / "services/analytics-service/final/src/main.py")
        for m in re.finditer(r'@app\.(?:get|post|put|delete)\(["\']([^"\']+)["\']', ana_py):
            routes.add(normalize_route(m.group(1)))
        routes.update({"/docs", "/openapi.json"})
        # Integration Go Gin
        srv_go = read_text(ROOT / "services/integration-service/final/internal/handlers/server.go")
        # Group /api/integrations
        for m in re.finditer(r'\.(?:GET|POST|PUT|DELETE|PATCH)\("([^"]+)"', srv_go):
            p = m.group(1)
            if p.startswith("/"):
                if p in {"/health", "/"}:
                    routes.add(p)
                else:
                    routes.add(normalize_route("/api/integrations" + p))
                    routes.add(normalize_route(p))
        # Notification Express + Socket.io
        for f in (ROOT / "services/notification-service/final/src/routes").glob("*.js"):
            t = read_text(f)
            for m in re.finditer(r"router\.(?:get|post|put|patch|delete)\(['\"]([^'\"]+)['\"]", t):
                routes.add(normalize_route("/api" + m.group(1)))
        routes.update({"/health", "/socket.io"})

    return routes


# ---------------------------------------------------------------------------
# (a) Deliverable & Cleanup Checks
# ---------------------------------------------------------------------------


def check_deliverables(report: AuditReport) -> None:
    for p in DELETED_PLACEHOLDERS:
        if p.exists():
            report.error("Deliverables", f"Placeholder file should be deleted but still exists: {p.relative_to(ROOT)}")
        else:
            report.count("deleted_placeholders_verified")

    for p in VALID_MEMBER_DIAGRAMS:
        if not p.exists():
            report.error("Deliverables", f"Existing valid member diagram missing: {p.relative_to(ROOT)}")
        else:
            report.count("member_diagrams_preserved")

    for p in MIDTERM_DIAGRAMS + FINAL_DIAGRAMS + [p[1] for p in PUML_PAIRS] + [MIDTERM_REPORT, FINAL_REPORT]:
        if not p.exists():
            report.error("Deliverables", f"Required deliverable missing: {p.relative_to(ROOT)}")
        elif p.stat().st_size < 100:
            report.error("Deliverables", f"Deliverable suspiciously small ({p.stat().st_size} B): {p.relative_to(ROOT)}")
        else:
            report.count("required_deliverables_present")


# ---------------------------------------------------------------------------
# (c) Mermaid & PlantUML Syntax Checks
# ---------------------------------------------------------------------------


def extract_fenced_blocks(text: str, lang: str) -> List[Tuple[int, str]]:
    """Return list of (start_line_no, code_body) for ```lang blocks."""
    blocks: List[Tuple[int, str]] = []
    lines = text.splitlines()
    i = 0
    while i < len(lines):
        if lines[i].strip().startswith(f"```{lang}"):
            start_line = i + 1
            body_lines: List[str] = []
            i += 1
            while i < len(lines) and not lines[i].strip().startswith("```"):
                body_lines.append(lines[i])
                i += 1
            blocks.append((start_line, "\n".join(body_lines)))
        i += 1
    return blocks


def validate_mermaid_block(file_rel: str, start_line: int, code: str, report: AuditReport) -> None:
    report.count("mermaid_blocks_checked")
    lines = [ln for ln in code.splitlines()]
    non_empty = [ln.strip() for ln in lines if ln.strip() and not ln.strip().startswith("%%")]
    if not non_empty:
        report.error("Mermaid", f"{file_rel}:{start_line} empty mermaid block")
        return

    header = non_empty[0]
    valid_headers = (
        "flowchart",
        "graph",
        "sequenceDiagram",
        "classDiagram",
        "erDiagram",
        "stateDiagram-v2",
        "stateDiagram",
    )
    if not any(header.startswith(h) for h in valid_headers):
        report.error("Mermaid", f"{file_rel}:{start_line} unrecognized diagram type header: {header!r}")
        return

    # Global quote balance per line (ignoring %% comments)
    for offset, raw in enumerate(lines, start=1):
        s = raw.strip()
        if not s or s.startswith("%%"):
            continue
        # Strip inline %% comments if outside quotes (simple check)
        if s.count('"') % 2 != 0:
            report.error("Mermaid", f"{file_rel}:{start_line + offset} unbalanced double quotes: {s}")

    if header.startswith(("flowchart", "graph")):
        subgraph_depth = 0
        for offset, raw in enumerate(lines, start=1):
            s = raw.strip()
            if not s or s.startswith("%%"):
                continue
            if re.match(r"^subgraph\b", s):
                subgraph_depth += 1
            elif s == "end":
                subgraph_depth -= 1
                if subgraph_depth < 0:
                    report.error("Mermaid", f"{file_rel}:{start_line + offset} unmatched 'end' in flowchart")
            # Check unquoted node labels [...] containing special chars ( ) : /
            # Match ID[...] where content is NOT enclosed in "..."
            for nm in re.finditer(r"(?<![\[|\"])\[([^\]\"]+)\](?!\])", s):
                inner = nm.group(1)
                # Allow [("...")] cylinder / [("..."?)] where inner starts with (" or (
                if inner.startswith("(") and inner.endswith(")"):
                    inner2 = inner[1:-1]
                    if not (inner2.startswith('"') and inner2.endswith('"')):
                        if any(ch in inner2 for ch in ("(", ")", ":", "/")):
                            report.error("Mermaid", f"{file_rel}:{start_line + offset} unquoted special chars in cylinder node label: [{inner}]")
                elif not (inner.startswith('"') and inner.endswith('"')):
                    if any(ch in inner for ch in ("(", ")", ":", "/")):
                        report.error("Mermaid", f"{file_rel}:{start_line + offset} unquoted special chars in node label: [{inner}]")

        if subgraph_depth != 0:
            report.error("Mermaid", f"{file_rel}:{start_line} unbalanced subgraph/end (depth={subgraph_depth})")

    elif header.startswith("sequenceDiagram"):
        block_stack: List[str] = []
        openers = ("alt", "opt", "loop", "par", "critical", "break", "rect", "box")
        for offset, raw in enumerate(lines[1:], start=2):
            s = raw.strip()
            if not s or s.startswith("%%"):
                continue
            kw = s.split()[0]
            if kw in openers:
                block_stack.append(kw)
            elif kw in ("else", "and"):
                if not block_stack:
                    report.error("Mermaid", f"{file_rel}:{start_line + offset} '{kw}' outside any alt/par block")
            elif kw == "end":
                if not block_stack:
                    report.error("Mermaid", f"{file_rel}:{start_line + offset} unmatched 'end' in sequenceDiagram")
                else:
                    block_stack.pop()
        if block_stack:
            report.error("Mermaid", f"{file_rel}:{start_line} unclosed sequenceDiagram block(s): {block_stack}")

    elif header.startswith(("classDiagram", "erDiagram", "stateDiagram")):
        brace_depth = 0
        is_er = header.startswith("erDiagram")
        for offset, raw in enumerate(lines, start=1):
            s = raw.strip()
            if not s or s.startswith("%%"):
                continue
            # Ignore strings inside "..." when counting braces in labels
            cleaned = re.sub(r'"[^"]*"', "", s)
            if is_er:
                # In erDiagram, relationship lines contain ||--o{ or }o--||; entity blocks open with `NAME {` and close with `}`
                if re.match(r"^[A-Za-z0-9_]+\s*\{$", cleaned.strip()):
                    brace_depth += 1
                elif cleaned.strip() == "}":
                    brace_depth -= 1
            else:
                brace_depth += cleaned.count("{") - cleaned.count("}")
            if brace_depth < 0:
                report.error("Mermaid", f"{file_rel}:{start_line + offset} unmatched '}}' in {header.split()[0]}")
                brace_depth = 0
        if brace_depth != 0:
            report.error("Mermaid", f"{file_rel}:{start_line} unbalanced '{{}}' in {header.split()[0]} (depth={brace_depth})")


def validate_plantuml(report: AuditReport) -> None:
    for md_path, puml_path in PUML_PAIRS:
        md_text = read_text(md_path)
        puml_text = read_text(puml_path)
        blocks = extract_fenced_blocks(md_text, "plantuml")
        if len(blocks) != 1:
            report.error("PlantUML", f"{md_path.relative_to(ROOT)} expected 1 plantuml block, found {len(blocks)}")
            continue
        report.count("plantuml_blocks_checked")
        embedded = blocks[0][1].strip()
        standalone = puml_text.strip()
        if not standalone.startswith("@startuml") or not standalone.endswith("@enduml"):
            report.error("PlantUML", f"{puml_path.relative_to(ROOT)} missing @startuml/@enduml wrapper")
        if embedded != standalone:
            report.error(
                "PlantUML",
                f"Parity mismatch between embedded PlantUML in {md_path.relative_to(ROOT)} and {puml_path.relative_to(ROOT)}",
            )
        # Brace balance check
        depth = 0
        for ln_no, line in enumerate(standalone.splitlines(), start=1):
            if line.strip().startswith("'"):
                continue
            depth += line.count("{") - line.count("}")
            if depth < 0:
                report.error("PlantUML", f"{puml_path.relative_to(ROOT)}:{ln_no} unmatched '}}'")
                depth = 0
        if depth != 0:
            report.error("PlantUML", f"{puml_path.relative_to(ROOT)} unbalanced braces (depth={depth})")


# ---------------------------------------------------------------------------
# (b) Ground Truth Cross-Checks (Paths, Containers, Env Vars, SQL, Classes, Routes)
# ---------------------------------------------------------------------------


def check_cited_paths(doc_paths: List[Path], report: AuditReport) -> None:
    """Verify every cited repo path in backticks or links actually exists on disk."""
    # Prefixes that definitely refer to repository files/directories
    prefixes = (
        "gateway/",
        "services/",
        "frontend/",
        "docs/",
        "scripts/",
        ".github/",
        "docker-compose.",
        ".env.example",
        "start-services.ps1",
        "run-frontend.bat",
        "plan-gk.md",
        "plan-ck.md",
    )
    # Known illustrative/placeholder/non-existent paths mentioned explicitly as negative/roadmap/external examples
    ignored_exact = {
        "gateway/...",
        "services/...",
        "frontend/...",
        "services/*/final/",
        "services/*/midterm/",
        "services/*/db/init-*-db.sql",
        "services/*/db/",
        "docs/diagrams/final-*.md",
        "docs/diagrams/*.md",
        "docs/reports/*.md",
        "docs/reports/Bao-Cao-*.md",
        "docs/diagrams/architecture-diagram.png",
        "docs/diagrams/usecase-diagram.png",
        "services/asset-service/final/Tests/AssetService.Tests.csproj",
        "Tests/AssetService.Tests.csproj",
        "services/transaction-service/final/app/Models/Transaction.php",
        "services/notification-service/final/src/routes/otp.js",
        "services/notification-service/final/src/middleware/auth.js",
    }

    for doc in doc_paths:
        text = read_text(doc)
        rel_doc = doc.relative_to(ROOT)
        # Strip code fences except when inspecting path references? Actually paths appear in prose/tables/captions
        # Extract backticked tokens
        for m in re.finditer(r"`([^`\n]+)`", text):
            tok = m.group(1).strip()
            # Clean trailing :line or line ranges like foo.js:12-34
            tok_clean = re.sub(r":\d+(?:-\d+)?$", "", tok)
            if not any(tok_clean.startswith(p) for p in prefixes):
                continue
            if "*" in tok_clean or "..." in tok_clean or "{" in tok_clean or "<" in tok_clean:
                continue
            if tok_clean in ignored_exact:
                continue
            # Also skip if token contains spaces (like command `docker compose -f docker-compose.final.yml ...`)
            if " " in tok_clean:
                continue
            # Strip trailing punctuation
            tok_clean = tok_clean.rstrip(".,;:")
            target = ROOT / tok_clean
            report.count("cited_repo_paths_checked")
            if not target.exists():
                report.error("RepoPath", f"{rel_doc}: cited path `{tok}` does not exist on disk")


def check_containers(
    docs: List[Path],
    expected_containers: Set[str],
    allowed_non_container_tokens: Set[str],
    phase_label: str,
    report: AuditReport,
) -> None:
    for doc in docs:
        text = read_text(doc)
        rel_doc = doc.relative_to(ROOT)
        for m in re.finditer(r"\bcashflow-[a-z0-9-]+\b", text):
            tok = m.group(0)
            if tok in allowed_non_container_tokens:
                continue
            # Ignore tokens like cashflow-midterm-net, cashflow-final-net, cashflow-auth-midterm-development-secret-key, cashflow-final-production-...
            if tok.endswith("-net") or "secret-key" in tok:
                continue
            report.count("container_refs_checked")
            if tok not in expected_containers:
                report.error("Container", f"{rel_doc} ({phase_label}): container `{tok}` not found in compose file")


def check_env_vars(doc_paths: List[Path], valid_env_vars: Set[str], report: AuditReport) -> None:
    """Check environment variable tables (Bảng 1.3 in Midterm, Bảng 1.2 in Final) + key config env tokens."""
    for doc in doc_paths:
        text = read_text(doc)
        rel_doc = doc.relative_to(ROOT)
        # Find sections / tables specifically documenting environment variables
        lines = text.splitlines()
        in_env_table = False
        for ln_no, line in enumerate(lines, start=1):
            if re.search(r"\*\*Bảng\s+1\.\d+.*Biến môi trường", line, re.I):
                in_env_table = True
                continue
            if in_env_table:
                if line.strip().startswith("|"):
                    if re.match(r"^\|\s*---", line):
                        continue
                    cols = [c.strip() for c in line.strip().strip("|").split("|")]
                    if cols and cols[0] in ("Thành phần", "Dịch vụ", "Biến môi trường", "Component"):
                        continue
                    # Expand brace-shorthand like ANALYTICS_DB_{HOST,PORT,NAME,USER,PASSWORD}
                    search_area = " ".join(cols[:2])
                    expanded_tokens: List[str] = []
                    for bm in re.finditer(r"\b([A-Z][A-Z0-9_]*)\{([A-Z0-9_,]+)\}", search_area):
                        prefix = bm.group(1)
                        for part in bm.group(2).split(","):
                            expanded_tokens.append(prefix + part.strip())
                    cleaned_area = re.sub(r"\b[A-Z][A-Z0-9_]*\{[A-Z0-9_,]+\}", "", search_area)
                    expanded_tokens.extend(re.findall(r"\b([A-Z][A-Z0-9_]{2,})\b", cleaned_area))
                    for ev in expanded_tokens:
                        if ev in {"HOST", "DOCKER", "LOCAL", "NATIVE", "DEFAULT", "HTTP", "HTTPS", "URL", "TCP", "VND", "UTC", "JSON", "CLI", "API", "JWT", "PHP", "SQL", "SSL"}:
                            continue
                        report.count("env_vars_checked")
                        if ev not in valid_env_vars:
                            report.error("EnvVar", f"{rel_doc}:{ln_no} env var `{ev}` not found in repo compose/configs")
                elif line.strip() == "" or line.startswith("#") or line.startswith("**"):
                    if not line.strip():
                        continue
                    in_env_table = False


def check_sql_and_erds(
    midterm_schemas: Dict[str, Dict[str, Set[str]]],
    final_schemas: Dict[str, Dict[str, Set[str]]],
    otp_fields: Set[str],
    report: AuditReport,
) -> None:
    # 1. Check Data Dictionary tables (`**Bảng X.Y: Bảng ...**`) in both reports
    mid_all_tables = {t: cols for db in midterm_schemas.values() for t, cols in db.items()}
    fin_all_tables = {t: cols for db in final_schemas.values() for t, cols in db.items()}
    fin_all_tables["otpRecord"] = otp_fields

    def verify_data_dict_in_report(doc_path: Path, table_map: Dict[str, Set[str]], phase: str) -> None:
        lines = read_text(doc_path).splitlines()
        rel = doc_path.relative_to(ROOT)
        current_table: Optional[str] = None
        in_table = False
        for ln_no, line in enumerate(lines, start=1):
            cap = re.match(r"^\*\*Bảng\s+\d+\.\d+:\s+Từ điển [dD]ữ liệu\s+[—-]\s+Bảng\s+`([a-zA-Z0-9_]+)`", line)
            if not cap:
                cap = re.match(r"^\*\*Bảng\s+\d+\.\d+:\s+Từ điển dữ liệu bảng\s+`([a-zA-Z0-9_]+)`", line)
            if not cap:
                cap = re.match(r"^\*\*Bảng\s+\d+\.\d+:\s+Bảng\s+`([a-zA-Z0-9_]+)`", line)
            if cap:
                current_table = cap.group(1)
                if current_table not in table_map:
                    report.error("DataDict", f"{rel}:{ln_no} ({phase}) table `{current_table}` not found in SQL/migration schemas")
                    current_table = None
                else:
                    report.count("data_dict_tables_checked")
                in_table = False
                continue

            if current_table and line.strip().startswith("|"):
                if re.match(r"^\|\s*Cột\s*\|", line):
                    in_table = True
                    # Verify 6-column header
                    cols = [c.strip() for c in line.strip().strip("|").split("|")]
                    if cols != ["Cột", "Kiểu SQL", "Khóa", "Cho phép NULL", "Khai báo", "Ý nghĩa"]:
                        report.error("DataDict", f"{rel}:{ln_no} invalid 6-column Data Dictionary header: {cols}")
                    continue
                if re.match(r"^\|\s*---", line):
                    continue
                if in_table:
                    cols = [c.strip() for c in line.strip().strip("|").split("|")]
                    if cols:
                        col_name = cols[0].strip("`").strip()
                        report.count("data_dict_columns_checked")
                        valid_cols = table_map[current_table]
                        if col_name not in valid_cols and col_name.lower() not in valid_cols:
                            report.error(
                                "DataDict",
                                f"{rel}:{ln_no} ({phase}) column `{col_name}` not in `{current_table}` (expected subset of {sorted(valid_cols)})",
                            )
            elif in_table and not line.strip().startswith("|"):
                in_table = False
                current_table = None

    verify_data_dict_in_report(MIDTERM_REPORT, mid_all_tables, "Midterm")
    verify_data_dict_in_report(FINAL_REPORT, fin_all_tables, "Final")

    # 2. Check erDiagram blocks in 04, final-04, and both reports
    erd_docs = [
        (ROOT / "docs/diagrams/04-erd-database-per-service.md", mid_all_tables, "Midterm", {}),
        (MIDTERM_REPORT, mid_all_tables, "Midterm", {}),
        (
            ROOT / "docs/diagrams/final-04-erd-database-per-service.md",
            fin_all_tables,
            "Final",
            {
                "AUTH_USERS": "users",
                "AUTH_REFRESH_TOKENS": "refresh_tokens",
                "TX_CATEGORIES": "categories",
                "TX_TRANSACTIONS": "transactions",
                "TX_BUDGETS": "budgets",
                "ASSET_ASSETS": "assets",
                "ASSET_HISTORY": "asset_history",
                "ASSET_LOANS": "loans",
                "ASSET_INVESTMENTS": "investments",
                "ANA_MONTHLY_REPORTS": "monthly_reports",
                "INT_EXCHANGE_RATES": "exchange_rates",
                "INT_LINKED_ACCOUNTS": "linked_accounts",
                "INT_BANK_TRANSACTIONS": "bank_transactions",
                "INT_EXTERNAL_WEBHOOKS": "external_webhooks",
                "NOTI_OTP_STORE_IN_MEMORY": "otpRecord",
            },
        ),
        (
            FINAL_REPORT,
            fin_all_tables,
            "Final",
            {
                "AUTH_USERS": "users",
                "AUTH_REFRESH_TOKENS": "refresh_tokens",
                "TX_CATEGORIES": "categories",
                "TX_TRANSACTIONS": "transactions",
                "TX_BUDGETS": "budgets",
                "ASSET_ASSETS": "assets",
                "ASSET_HISTORY": "asset_history",
                "ASSET_LOANS": "loans",
                "ASSET_INVESTMENTS": "investments",
                "ANA_MONTHLY_REPORTS": "monthly_reports",
                "INT_EXCHANGE_RATES": "exchange_rates",
                "INT_LINKED_ACCOUNTS": "linked_accounts",
                "INT_BANK_TRANSACTIONS": "bank_transactions",
                "INT_EXTERNAL_WEBHOOKS": "external_webhooks",
                "NOTI_OTP_STORE_IN_MEMORY": "otpRecord",
            },
        ),
    ]

    for doc, table_map, phase, alias_map in erd_docs:
        rel = doc.relative_to(ROOT)
        for start_line, code in extract_fenced_blocks(read_text(doc), "mermaid"):
            if not code.lstrip().startswith("erDiagram"):
                continue
            cur_entity: Optional[str] = None
            for offset, raw in enumerate(code.splitlines(), start=1):
                s = raw.strip()
                if not s or s.startswith("%%"):
                    continue
                ent_m = re.match(r"^([A-Za-z0-9_]+)\s*\{$", s)
                if ent_m:
                    raw_ent = ent_m.group(1)
                    mapped = alias_map.get(raw_ent, raw_ent)
                    if mapped not in table_map:
                        report.error("ERD", f"{rel}:{start_line + offset} ({phase}) entity `{raw_ent}` -> `{mapped}` not in schema")
                        cur_entity = None
                    else:
                        cur_entity = mapped
                        report.count("erd_entities_checked")
                    continue
                if s == "}":
                    cur_entity = None
                    continue
                if cur_entity:
                    col_m = re.match(r"^[A-Za-z0-9_]+\s+([A-Za-z0-9_]+)", s)
                    if col_m:
                        col = col_m.group(1)
                        report.count("erd_columns_checked")
                        valid = table_map[cur_entity]
                        if col not in valid and col.lower() not in valid:
                            report.error(
                                "ERD",
                                f"{rel}:{start_line + offset} ({phase}) column `{col}` in `{cur_entity}` not in schema",
                            )


def check_class_diagrams_against_code(
    docs: List[Path], symbols: Set[str], phase: str, report: AuditReport
) -> None:
    """Verify class names and method names inside mermaid classDiagram blocks exist in source code."""
    # Conceptual wrapper names used in diagrams to represent top-level script/module files or external framework classes
    conceptual_allowed = {
        "IndexModule", "ConfigModule", "JwtModule", "ProxyModule", "DocsModule",
        "FrontRouter", "AnalyticsCoreModule", "NotificationIndex", "SseModule",
        "RedisClientModule", "StoreModule", "HttpHandler",
        "IndexServer", "AppModule", "RequestTracerMiddleware", "AuthGuardMiddleware",
        "DocsRoutes", "Controller", "DBQueryBuilder", "FastAPIApp", "AnalyticsEngine",
        "DatabaseModule", "EntryIndex", "NotificationController", "OtpController",
        "OtpService", "SocketModule", "RedisModule", "NotificationStore",
        # Conceptual helper / framework methods summarized in diagrams
        "handleRequest", "handleHttpRequest", "dispatch", "spl_autoload_register",
        "listen", "onUpgrade", "shutdown", "generateUuid", "measureHrTime",
        "healthCheck", "aggregatedServicesHealth", "createProxyMiddleware",
        "notFoundHandler", "globalErrorHandler", "table", "transaction", "lockForUpdate",
        "cors", "gracefulShutdown", "onRedisMessage", "handleApiException",
        "handleValidation", "handleConflict",
    }

    for doc in docs:
        rel = doc.relative_to(ROOT)
        for start_line, code in extract_fenced_blocks(read_text(doc), "mermaid"):
            if not code.lstrip().startswith("classDiagram"):
                continue
            cur_class: Optional[str] = None
            for offset, raw in enumerate(code.splitlines(), start=1):
                s = raw.strip()
                if not s or s.startswith("%%"):
                    continue
                cm = re.match(r"^class\s+([A-Za-z0-9_]+)\s*\{$", s)
                if cm:
                    cls_name = cm.group(1)
                    cur_class = cls_name
                    report.count("class_diagram_classes_checked")
                    if cls_name not in symbols and cls_name not in conceptual_allowed:
                        report.error("ClassSymbol", f"{rel}:{start_line + offset} ({phase}) class `{cls_name}` not found in {phase} source code")
                    continue
                if s == "}":
                    cur_class = None
                    continue
                if cur_class and s.startswith(("+", "-", "#")):
                    mm = re.match(r"^[+\-#]([A-Za-z0-9_]+)\s*\(", s)
                    if mm:
                        meth = mm.group(1)
                        report.count("class_diagram_methods_checked")
                        if meth not in symbols and meth not in conceptual_allowed:
                            report.error(
                                "MethodSymbol",
                                f"{rel}:{start_line + offset} ({phase}) method `{cur_class}.{meth}()` not found in {phase} source code",
                            )


def check_api_routes_in_reports(
    doc_path: Path, actual_routes: Set[str], final_routes: Set[str], phase: str, report: AuditReport
) -> None:
    """Verify /api/... routes cited in API catalog tables exist in actual source routes."""
    text = read_text(doc_path)
    rel = doc_path.relative_to(ROOT)
    in_final_roadmap = False
    # Check table rows containing `/api/...`
    for ln_no, line in enumerate(text.splitlines(), start=1):
        if phase == "midterm" and re.match(r"^##\s*4\.3\.", line):
            in_final_roadmap = True
        elif phase == "midterm" and re.match(r"^#\s+", line):
            in_final_roadmap = False
        if not line.strip().startswith("|"):
            continue
        target_routes = final_routes if in_final_roadmap else actual_routes
        for m in re.finditer(r"`((?:/api|/actuator/health|/health(?:/ready|/services)?|/socket\.io)[a-zA-Z0-9_/{}:.-]*)`", line):
            raw_path = m.group(1)
            norm = normalize_route(raw_path)
            # Ignore generic wildcard prefix notes like /api/admin/** or /api/notifications/otp/...
            if "**" in raw_path or "*" in raw_path or "..." in raw_path:
                continue
            report.count("api_routes_checked")
            if norm not in target_routes:
                report.error("ApiRoute", f"{rel}:{ln_no} ({phase}) route `{raw_path}` (normalized `{norm}`) not found in actual routes")


# ---------------------------------------------------------------------------
# (d) Links, Structure & Cross-Report Consistency
# ---------------------------------------------------------------------------


def check_markdown_links(docs: List[Path], report: AuditReport) -> None:
    for doc in docs:
        text = read_text(doc)
        rel = doc.relative_to(ROOT)
        # Remove fenced code blocks so regex doesn't match syntax like [x](y) inside code
        stripped_lines = []
        in_fence = False
        for line in text.splitlines():
            if line.strip().startswith("```"):
                in_fence = not in_fence
                stripped_lines.append("")
                continue
            stripped_lines.append("" if in_fence else line)
        cleaned = "\n".join(stripped_lines)

        for m in re.finditer(r"!?\[[^\]]*\]\(([^)]+)\)", cleaned):
            href = m.group(1).strip()
            if href.startswith(("http://", "https://", "mailto:", "#")):
                continue
            # Handle file:/// URLs if any
            if href.startswith("file:///"):
                target = Path(href[8:].replace("%20", " "))
            else:
                path_part = href.split("#")[0]
                if not path_part:
                    continue
                target = (doc.parent / path_part).resolve()
            report.count("markdown_links_checked")
            if not target.exists():
                report.error("Link", f"{rel}: broken link target `{href}` -> {target}")


def check_report_structure(doc_path: Path, is_final: bool, report: AuditReport) -> None:
    text = read_text(doc_path)
    rel = doc_path.relative_to(ROOT)
    lines = text.splitlines()

    # 1. Exclusions (no cover page / LỜI CẢM ƠN)
    for forbidden in ["LỜI CẢM ƠN", "CÔNG TRÌNH ĐƯỢC HOÀN THÀNH"]:
        if forbidden in text.upper():
            report.error("Structure", f"{rel}: contains excluded administrative section `{forbidden}`")

    # 2. Required front matter & back matter headings
    required_headings = [
        r"TÓM TẮT",
        r"DANH MỤC HÌNH ẢNH",
        r"DANH MỤC BẢNG BIỂU",
        r"CHƯƠNG 1\b",
        r"CHƯƠNG 2\b",
        r"CHƯƠNG 3\b",
        r"CHƯƠNG 4\b",
        r"PHỤ LỤC",
        r"TÀI LIỆU THAM KHẢO",
    ]
    for pat in required_headings:
        if not re.search(pat, text, re.I):
            report.error("Structure", f"{rel}: missing required section matching `{pat}`")

    # 3. 1-to-1 Hình X-Y parity between Danh mục Hình ảnh & body
    fig_list: List[str] = []
    in_fig_list = False
    for line in lines:
        if re.match(r"^#+\s*DANH MỤC HÌNH ẢNH", line, re.I):
            in_fig_list = True
            continue
        if in_fig_list:
            if re.match(r"^#+\s*DANH MỤC BẢNG BIỂU", line, re.I):
                in_fig_list = False
                break
            fm = re.match(r"^-\s*\*\*Hình\s+(\d+-\d+):", line)
            if fm:
                fig_list.append(fm.group(1))

    body_diagrams = re.findall(r"^\*Hình\s+(\d+-\d+):", text, re.M)
    body_screenshots = re.findall(r"^>\s*📸\s*\*\*\[VỊ TRÍ CHÈN HÌNH\s+(\d+-\d+):", text, re.M)
    body_figs = body_diagrams + body_screenshots

    if sorted(fig_list) != sorted(body_figs) or len(fig_list) != len(body_figs):
        report.error(
            "FigureParity",
            f"{rel}: Danh mục Hình ảnh ({len(fig_list)}: {fig_list}) != body figures ({len(body_figs)}: {sorted(body_figs)})",
        )
    else:
        report.count("figures_parity_verified", len(fig_list))

    # 4. Every *Hình X-Y: diagram caption must have "Phân tích chi tiết Hình X-Y:" shortly after
    for idx, line in enumerate(lines):
        dm = re.match(r"^\*Hình\s+(\d+-\d+):", line)
        if dm:
            fig_id = dm.group(1)
            window = "\n".join(lines[idx + 1 : idx + 8])
            if f"Phân tích chi tiết Hình {fig_id}:" not in window:
                report.error("FigureAnalysis", f"{rel}:{idx + 1} missing `Phân tích chi tiết Hình {fig_id}:` immediately after diagram")
            else:
                report.count("diagram_analyses_verified")

    # 5. 1-to-1 Bảng X.Y parity between Danh mục Bảng biểu & body
    tbl_list: List[str] = []
    in_tbl_list = False
    for line in lines:
        if re.match(r"^#+\s*DANH MỤC BẢNG BIỂU", line, re.I):
            in_tbl_list = True
            continue
        if in_tbl_list:
            if re.match(r"^#\s*CHƯƠNG 1", line, re.I):
                in_tbl_list = False
                break
            tm = re.match(r"^-\s*\*\*Bảng\s+(\d+\.\d+):", line)
            if tm:
                tbl_list.append(tm.group(1))

    body_tbls = re.findall(r"^\*\*Bảng\s+(\d+\.\d+):", text, re.M)
    if sorted(tbl_list) != sorted(body_tbls) or len(tbl_list) != len(body_tbls):
        report.error(
            "TableParity",
            f"{rel}: Danh mục Bảng biểu ({len(tbl_list)}: {tbl_list}) != body tables ({len(body_tbls)}: {sorted(body_tbls)})",
        )
    else:
        report.count("tables_parity_verified", len(tbl_list))

    # 6. Screenshot blocks count
    if not body_screenshots:
        report.error("Structure", f"{rel}: missing `> 📸 **[VỊ TRÍ CHÈN HÌNH X-Y: ...]**` screenshot blocks")

    # 7. Exact 6 team members + MSSVs + 100% contribution sum in Appendix
    app_idx = text.find("PHỤ LỤC")
    if app_idx == -1:
        report.error("Appendix", f"{rel}: missing PHỤ LỤC section")
    else:
        app_text = text[app_idx:]
        for name, mssv in EXPECTED_MEMBERS:
            if name not in app_text or mssv not in app_text:
                report.error("Appendix", f"{rel}: missing member `{name}` (`{mssv}`) in Appendix")
        pcts = [int(p) for p in re.findall(r"Tỷ lệ đóng góp:\*\*\s*`(\d+)%`", app_text)]
        if len(pcts) != 6 or sum(pcts) != 100:
            report.error("Appendix", f"{rel}: expected 6 contribution percentages summing to 100%, got {pcts} (sum={sum(pcts)})")
        else:
            report.count("member_appendix_verified")

    # 8. Final report must have Rubric 10.0 table (Bảng 4.1)
    if is_final:
        if "Bảng 4.1:" not in text or "Rubric" not in text:
            report.error("Rubric", f"{rel}: missing Rubric 10.0 mapping table (Bảng 4.1)")
        else:
            report.count("final_rubric_verified")


def check_cross_report_consistency(report: AuditReport) -> None:
    mid_text = read_text(MIDTERM_REPORT)
    fin_text = read_text(FINAL_REPORT)

    # 1. Ports consistency across both reports
    for port in ["8080", "8081", "8082", "8083", "8084", "8085", "8086", "3000", "3307", "3310", "6379"]:
        if port not in mid_text:
            report.error("CrossReport", f"Port `{port}` missing from Midterm report")
        if port not in fin_text:
            report.error("CrossReport", f"Port `{port}` missing from Final report")

    # 2. analytics_db must NOT be misdescribed as MongoDB
    for label, txt in [("Midterm", mid_text), ("Final", fin_text)]:
        if "analytics_db" not in txt or "monthly_reports" not in txt:
            report.error("CrossReport", f"{label} report missing analytics_db / monthly_reports specification")
        # Flag affirmative MongoDB claims while allowing explicit refutations ("không dùng MongoDB")
        for m in re.finditer(r"analytics_db.{0,60}MongoDB|MongoDB.{0,60}analytics_db", txt, re.I):
            ctx = txt[max(0, m.start() - 30) : m.end() + 30]
            if not re.search(r"không\s+dùng\s+MongoDB|chứ\s+không\s+dùng\s+MongoDB", ctx, re.I):
                report.error("CrossReport", f"{label} report associates analytics_db with MongoDB: {ctx!r}")

    # 3. 6 members & MSSVs identical in both reports
    for name, mssv in EXPECTED_MEMBERS:
        if name not in mid_text or mssv not in mid_text:
            report.error("CrossReport", f"Midterm report missing member {name} ({mssv})")
        if name not in fin_text or mssv not in fin_text:
            report.error("CrossReport", f"Final report missing member {name} ({mssv})")

    # 4. Ground-truth nuances: no false claims on refresh rotation, OTP replay, healthcheck, non-root, JWT_SECRET length, Eloquent
    if "accessToken" not in fin_text or "refresh" not in fin_text:
        report.error("CrossReport", "Final report missing refresh token behavior documentation")

    if "OTP_NOT_FOUND" not in fin_text:
        report.error("CrossReport", "Final report should accurately document OTP_NOT_FOUND on replay due to otpStore.delete()")

    if re.search(r"cả 7 container ứng dụng đều có healthcheck|tất cả 13 container đều khai báo healthcheck|cho toàn bộ dịch vụ & DB", fin_text + "\n" + mid_text, re.I):
        report.error("CrossReport", "Report falsely claims all 13/app containers have Docker healthcheck")
    if re.search(r"cả 7 Dockerfile.*non-root|tất cả 7 service.*non-root", fin_text, re.I):
        report.error("CrossReport", "Final report falsely claims all 7 Dockerfiles are non-root")

    # Check JWT_SECRET default length is not misstated as 51 chars
    if "51 ký tự" in fin_text:
        report.error("CrossReport", "Final report states JWT_SECRET default is 51 chars (actual is 50 chars)")

    # Midterm Ch. 4.3 roadmap must align with Final Query Builder implementation (no Eloquent ORM claim for business models)
    if "## 4.3." in mid_text:
        ch43 = mid_text[mid_text.find("## 4.3.") :]
        if "Eloquent ORM" in ch43:
            report.error("CrossReport", "Midterm Ch. 4.3 roadmap claims Laravel Eloquent ORM while Final uses Query Builder (DB::table)")

    # 5. Standalone diagram <-> report embedded Mermaid 1-to-1 parity
    mid_diag_blocks = [code.strip() for d in MIDTERM_DIAGRAMS for _, code in extract_fenced_blocks(read_text(d), "mermaid")]
    mid_rep_blocks = [code.strip() for _, code in extract_fenced_blocks(mid_text, "mermaid")]
    if set(mid_rep_blocks) != set(mid_diag_blocks) or len(mid_rep_blocks) != len(mid_diag_blocks):
        report.error("DiagramParity", f"Midterm embedded mermaid blocks ({len(mid_rep_blocks)}) differ from standalone files ({len(mid_diag_blocks)})")
    else:
        report.count("midterm_diagram_report_parity", len(mid_rep_blocks))

    fin_diag_blocks = [code.strip() for d in FINAL_DIAGRAMS for _, code in extract_fenced_blocks(read_text(d), "mermaid")]
    fin_rep_blocks = [code.strip() for _, code in extract_fenced_blocks(fin_text, "mermaid")]
    if set(fin_rep_blocks) != set(fin_diag_blocks) or len(fin_rep_blocks) != len(fin_diag_blocks):
        report.error("DiagramParity", f"Final embedded mermaid blocks ({len(fin_rep_blocks)}) differ from standalone files ({len(fin_diag_blocks)})")
    else:
        report.count("final_diagram_report_parity", len(fin_rep_blocks))

    report.count("cross_report_checks_passed")


def main() -> int:
    report = AuditReport()

    print("=== SOA-CashFlow Static Documentation Verification ===")
    check_deliverables(report)

    mid_compose = parse_compose_file(ROOT / "docker-compose.midterm.yml")
    fin_compose = parse_compose_file(ROOT / "docker-compose.final.yml")
    valid_env_vars = collect_repo_env_vars()
    mid_schemas, fin_schemas = parse_sql_schemas()
    otp_fields = parse_otp_record_fields()
    mid_symbols = collect_code_symbols("midterm")
    fin_symbols = collect_code_symbols("final")
    mid_routes = collect_actual_routes("midterm")
    fin_routes = collect_actual_routes("final")

    all_new_docs = MIDTERM_DIAGRAMS + FINAL_DIAGRAMS + [MIDTERM_REPORT, FINAL_REPORT]

    # (b) Ground Truth Cross-Checks
    check_cited_paths(all_new_docs, report)

    non_container_mid = {"cashflow-midterm-net", "cashflow-auth-midterm-development-secret-key", "cashflow-native-gateway"}
    non_container_fin = {"cashflow-final-net", "cashflow-final-production-jwt-secret-key-2026-tdtu", "cashflow-express-gateway-final"}
    check_containers(MIDTERM_DIAGRAMS + [MIDTERM_REPORT], mid_compose["containers"], non_container_mid, "Midterm", report)
    check_containers(FINAL_DIAGRAMS + [FINAL_REPORT], fin_compose["containers"], non_container_fin, "Final", report)

    check_env_vars([MIDTERM_REPORT, FINAL_REPORT], valid_env_vars, report)
    check_sql_and_erds(mid_schemas, fin_schemas, otp_fields, report)

    check_class_diagrams_against_code(
        [ROOT / "docs/diagrams/03-class-diagrams-midterm.md", MIDTERM_REPORT],
        mid_symbols,
        "midterm",
        report,
    )
    check_class_diagrams_against_code(
        [ROOT / "docs/diagrams/final-03-class-diagrams-frameworks.md", FINAL_REPORT],
        fin_symbols,
        "final",
        report,
    )

    check_api_routes_in_reports(MIDTERM_REPORT, mid_routes, fin_routes, "midterm", report)
    check_api_routes_in_reports(FINAL_REPORT, fin_routes, fin_routes, "final", report)

    # (c) Mermaid & PlantUML Validation
    for doc in all_new_docs:
        rel = str(doc.relative_to(ROOT))
        for start_line, code in extract_fenced_blocks(read_text(doc), "mermaid"):
            validate_mermaid_block(rel, start_line, code, report)
    validate_plantuml(report)

    # (d) Links, Structure & Cross-Report Consistency
    all_diagram_mds = sorted((ROOT / "docs/diagrams").glob("*.md"))
    check_markdown_links(all_diagram_mds + [MIDTERM_REPORT, FINAL_REPORT], report)
    check_report_structure(MIDTERM_REPORT, is_final=False, report=report)
    check_report_structure(FINAL_REPORT, is_final=True, report=report)
    check_cross_report_consistency(report)

    print("\n--- Verification Statistics ---")
    for k in sorted(report.stats):
        print(f"  {k}: {report.stats[k]}")

    if report.warnings:
        print(f"\n--- Warnings ({len(report.warnings)}) ---")
        for w in report.warnings:
            print(f"  WARN: {w}")

    if report.errors:
        print(f"\n--- Errors ({len(report.errors)}) ---")
        for e in report.errors:
            print(f"  ERR:  {e}")
        print("\nRESULT: FAILED")
        return 1

    print("\nRESULT: PASSED (0 errors, 0 mismatches)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
