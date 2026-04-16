<div class="sidebar-brand">
    <div class="sidebar-brand-logo">
        <img src="{{ asset('images/atg-icon.png') }}" alt="ATG Logo">
    </div>
    <div>
        <div class="sidebar-brand-name">ATG POS</div>
        <div class="sidebar-brand-sub">Back Office Workspace</div>
    </div>
</div>

<div class="sidebar-section">
    <div class="sidebar-title">Dashboard</div>
    <div class="sidebar-menu">
        <a href="{{ route('backoffice.index') }}" class="sidebar-link {{ request()->routeIs('backoffice.index') ? 'active' : '' }}">
            <span class="sidebar-nav-icon orange">
                <svg viewBox="0 0 24 24">
                    <path d="M4 20h16"></path>
                    <path d="M6 20V8l6-4 6 4v12"></path>
                    <path d="M9 20v-5h6v5"></path>
                </svg>
            </span>
            <span>Dashboard</span>
        </a>
    </div>
</div>

<div class="sidebar-section">
    <div class="sidebar-title">Master Data</div>
    <div class="sidebar-menu">
        <a href="{{ route('backoffice.outlets.index') }}" class="sidebar-link {{ request()->routeIs('backoffice.outlets.*') ? 'active' : '' }}">
            <span class="sidebar-nav-icon orange">
                <svg viewBox="0 0 24 24">
                    <path d="M4 20h16"></path>
                    <path d="M6 20V8l6-4 6 4v12"></path>
                    <path d="M9 20v-5h6v5"></path>
                </svg>
            </span>
            <span>Outlets</span>
        </a>

        <a href="{{ route('backoffice.warehouses.index') }}" class="sidebar-link {{ request()->routeIs('backoffice.warehouses.*') ? 'active' : '' }}">
            <span class="sidebar-nav-icon green">
                <svg viewBox="0 0 24 24">
                    <path d="M3 10.5 12 5l9 5.5"></path>
                    <path d="M5 9.5V19h14V9.5"></path>
                    <path d="M9 19v-5h6v5"></path>
                </svg>
            </span>
            <span>Warehouses</span>
        </a>

        <a href="{{ route('backoffice.users.index') }}" class="sidebar-link {{ request()->routeIs('backoffice.users.*') ? 'active' : '' }}">
            <span class="sidebar-nav-icon violet">
                <svg viewBox="0 0 24 24">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9.5" cy="7" r="4"></circle>
                    <path d="M20 8v6"></path>
                    <path d="M17 11h6"></path>
                </svg>
            </span>
            <span>Users</span>
        </a>

        <a href="{{ route('backoffice.products.index') }}" class="sidebar-link {{ request()->routeIs('backoffice.products.*') ? 'active' : '' }}">
            <span class="sidebar-nav-icon blue">
                <svg viewBox="0 0 24 24">
                    <rect x="4" y="4" width="16" height="16" rx="3"></rect>
                    <path d="M8 9h8"></path>
                    <path d="M8 13h8"></path>
                    <path d="M8 17h4"></path>
                </svg>
            </span>
            <span>Products</span>
        </a>

        <a href="{{ route('backoffice.variants.index') }}" class="sidebar-link {{ request()->routeIs('backoffice.variants.*') ? 'active' : '' }}">
            <span class="sidebar-nav-icon violet">
                <svg viewBox="0 0 24 24">
                    <path d="M7 7h10"></path>
                    <path d="M7 12h10"></path>
                    <path d="M7 17h6"></path>
                    <path d="M5 7h.01"></path>
                    <path d="M5 12h.01"></path>
                    <path d="M5 17h.01"></path>
                </svg>
            </span>
            <span>Variants</span>
        </a>

        <a href="{{ route('backoffice.ingredients.index') }}" class="sidebar-link {{ request()->routeIs('backoffice.ingredients.*') ? 'active' : '' }}">
            <span class="sidebar-nav-icon green">
                <svg viewBox="0 0 24 24">
                    <path d="M7 4h10"></path>
                    <path d="M9 4v5l-4 7a3 3 0 0 0 2.6 4.5h8.8A3 3 0 0 0 19 16l-4-7V4"></path>
                    <path d="M8 14h8"></path>
                </svg>
            </span>
            <span>Ingredients</span>
        </a>
    </div>
</div>

<div class="sidebar-section">
    <div class="sidebar-title">Production</div>
    <div class="sidebar-menu">
        <a href="{{ route('backoffice.recipes.index') }}" class="sidebar-link {{ request()->routeIs('backoffice.recipes.*') ? 'active' : '' }}">
            <span class="sidebar-nav-icon orange">
                <svg viewBox="0 0 24 24">
                    <path d="M6 4h12"></path>
                    <path d="M8 4v16"></path>
                    <path d="M16 4v16"></path>
                    <path d="M8 9h8"></path>
                    <path d="M8 14h8"></path>
                </svg>
            </span>
            <span>Recipes</span>
        </a>

        <a href="{{ route('backoffice.production-recipes.index') }}" class="sidebar-link {{ request()->routeIs('backoffice.production-recipes.*') ? 'active' : '' }}">
            <span class="sidebar-nav-icon violet">
                <svg viewBox="0 0 24 24">
                    <path d="M7 4h10"></path>
                    <path d="M9 4v4"></path>
                    <path d="M15 4v4"></path>
                    <path d="M5 10h14"></path>
                    <path d="M6 20h12"></path>
                    <path d="M8 14h8"></path>
                </svg>
            </span>
            <span>Production Recipes</span>
        </a>

        <a href="{{ route('backoffice.productions.index') }}" class="sidebar-link {{ request()->routeIs('backoffice.productions.*') ? 'active' : '' }}">
            <span class="sidebar-nav-icon green">
                <svg viewBox="0 0 24 24">
                    <path d="M4 7h16"></path>
                    <path d="M6 7v10h12V7"></path>
                    <path d="M9 12h6"></path>
                    <path d="M12 9v6"></path>
                </svg>
            </span>
            <span>Productions</span>
        </a>
    </div>
</div>

<div class="sidebar-section">
    <div class="sidebar-title">Inventory</div>
    <div class="sidebar-menu">
        <a href="{{ route('backoffice.stock-balances.index') }}" class="sidebar-link {{ request()->routeIs('backoffice.stock-balances.*') ? 'active' : '' }}">
            <span class="sidebar-nav-icon blue">
                <svg viewBox="0 0 24 24">
                    <rect x="4" y="4" width="16" height="16" rx="3"></rect>
                    <path d="M8 9h8"></path>
                    <path d="M8 13h8"></path>
                    <path d="M8 17h4"></path>
                </svg>
            </span>
            <span>Inventory Control</span>
        </a>

        <a href="{{ route('backoffice.stock-movements.index') }}" class="sidebar-link {{ request()->routeIs('backoffice.stock-movements.*') ? 'active' : '' }}">
            <span class="sidebar-nav-icon violet">
                <svg viewBox="0 0 24 24">
                    <path d="M5 17h14"></path>
                    <path d="M5 12h10"></path>
                    <path d="M5 7h6"></path>
                    <path d="M17 9l2-2 2 2"></path>
                    <path d="M19 7v10"></path>
                </svg>
            </span>
            <span>Stock Movements</span>
        </a>

        <a href="{{ route('backoffice.transfers.index') }}" class="sidebar-link {{ request()->routeIs('backoffice.transfers.*') ? 'active' : '' }}">
            <span class="sidebar-nav-icon orange">
                <svg viewBox="0 0 24 24">
                    <path d="M7 7h11"></path>
                    <path d="m14 4 4 3-4 3"></path>
                    <path d="M17 17H6"></path>
                    <path d="m10 14-4 3 4 3"></path>
                </svg>
            </span>
            <span>Transfers</span>
        </a>
    </div>
</div>

<div class="sidebar-section">
    <div class="sidebar-title">Sales</div>
    <div class="sidebar-menu">
        <a href="{{ route('backoffice.transactions.index') }}" class="sidebar-link {{ request()->routeIs('backoffice.transactions.*') ? 'active' : '' }}">
            <span class="sidebar-nav-icon green">
                <svg viewBox="0 0 24 24">
                    <path d="M4 7h16"></path>
                    <rect x="3" y="5" width="18" height="14" rx="3"></rect>
                    <path d="M7 15h3"></path>
                    <path d="M14 15h3"></path>
                </svg>
            </span>
            <span>Transactions</span>
        </a>

        <a href="{{ route('backoffice.shifts.index') }}" class="sidebar-link {{ request()->routeIs('backoffice.shifts.*') ? 'active' : '' }}">
            <span class="sidebar-nav-icon blue">
                <svg viewBox="0 0 24 24">
                    <path d="M12 6v6l4 2"></path>
                    <circle cx="12" cy="12" r="8"></circle>
                </svg>
            </span>
            <span>Shifts</span>
        </a>
    </div>
</div>

<div class="sidebar-footer">
    {{ $user->name ?? 'User' }} • {{ $user->role->name ?? '-' }}<br>
    {{ $user->outlet->name ?? 'Back Office Access' }}
</div>