### Phase 1: Foundation & Tenancy (The Scaffolding)
**Goal:** Establish the absolute directory structure, get the Dockerized environments communicating, and allow users to create and join a shared Space.

**1. The Monorepo Directory Structure**
Create a root folder called `poruko` which will contain everything, orchestrated by a root `docker-compose.yml`:
```text
poruko/
├── api/                # The Laravel API API
│   └── .env            # Backend specific env vars
├── web/                # The React + Vite UI
│   └── .env            # Frontend env vars (e.g., VITE_API_BASE_URL)
├── docker-compose.yml  # Root orchestrator for homelab deployment
├── .gitignore
└── README.md
```

**2. Infrastructure & Backend (Laravel)**
*   **Initialization:** Run the Laravel installer inside the `api/` directory.
*   **Docker Orchestration:** Define your services in `docker-compose.yml`: `poruko-api` (PHP/Laravel), `poruko-web` (Node/Vite/Nginx), `poruko-db` (PostgreSQL), and `poruko-redis` (Redis).
*   **Authentication Scaffold:** Install and configure Laravel Sanctum, ensuring CORS settings accept requests from your frontend container.
*   **Database & APIs:** Create migrations and models for `users`, `ledgers`, and the `ledger_user` pivot table. Build the basic auth and tenancy endpoints (e.g., Register/Login, Create Ledger, Invite User).

**3. Frontend (React)**
*   **Initialization:** Run Vite with the `react-ts` template from the root directory.
*   **Core Libraries:** Set up **Tailwind CSS**, initialize **shadcn/ui**, and install React Router and **@tanstack/react-query**.
*   **API Client:** Create an Axios instance (or fetch wrapper) that automatically attaches the Sanctum Bearer token to all requests.
*   **Initial UI:** Build Auth screens, a Dashboard shell with navigation, and Tenancy UI (Create/Switch Space modals).

---

### Phase 2: Core Accounting Engine (The Ledger)
**Goal:** Prove the double-entry math works. Users can create accounts and log manual expenses.

*   **Backend:** Create migrations for `accounts`, `transactions`, and `postings`. Write the **`LedgerPostingService`** to handle transaction requests, calculate the split (starting with equal and individual rules), and wrap database inserts in a `DB::transaction()` to ensure total debits always equal total credits. Build the necessary CRUD APIs.
*   **Frontend:** Build the "Manage Accounts" page, an "Add Expense" modal (using shadcn forms and Zod validation), and a data table to view transaction history.

---

### Phase 3: Financial Profiles & The Proportional Rule (The Brains)
**Goal:** Introduce dynamic incomes and unlock the "Proportional" split rule.

*   **Backend:** Create migrations for `financial_profiles` with JSONB columns for incomes and deductions. Write the **`FinancialProfileService`** to calculate "Shareable Income" on the fly based on a given date. Upgrade the `LedgerPostingService` so it understands the proportional split rule.
*   **Frontend:** Build a "My Finances" page where users can add salary and deductions to dynamic form arrays. Update the "Add Expense" modal to include the new "Proportional" option.

---

### Phase 4: The Settlement Engine (The Lock)
**Goal:** Calculate who owes what at the end of the month and freeze the history.

*   **Backend:** Create migrations for `settlements`. Write the **`SettlementService`** with a `preview()` method (to calculate debts without saving) and an `execute()` method (to create transfer postings and save the settlement record). Update the `FinancialProfileService` to handle the bi-temporal "Branch vs. In-Place Edit" logic.
*   **Frontend:** Build the "Settle Up" dashboard widget to show pool progress and debts. Add a "Confirm Settlement" button that locks the month.

---

### Phase 5: Automation (The Helpers) ✅
**Goal:** Let the system handle rent and fixed bills automatically.

*   **Backend:** `recurring_transactions` table with bi-temporal modeling (`valid_from`/`valid_to`), soft deletes, and `credit_account_id`/`debit_account_id`. Artisan command `ledger:materialize` reads active blueprints and materializes them via `PostRecurringTransactionAction` (reuses `PostManualTransactionAction`). Supports weekly, monthly, and annual frequency. Scheduler runs hourly for correct timezone handling. CRUD API with bi-temporal edits (PATCH closes current row, creates new).
*   **Frontend:** "Recurring Bills" tab with list, add form, edit form, and delete. CalendarClock icon in nav.

---

### Phase 6: AI Ingestion (The Magic)
**Goal:** Bring Your Own Key (BYOK) bank statement parsing.

*   **Backend:** Create migrations for `pending_transactions`. Set up Laravel Horizon or a Redis queue worker to run the **`ProcessBankStatementJob`**, which passes the CSV to OpenAI/Anthropic APIs and prompts for structured JSON. Build APIs for uploading CSVs and managing pending transactions.
*   **Frontend:** Build a Settings page to save the LLM API Key, a file upload dropzone for the CSV, and a "Pending Review" table where users can approve or override AI-suggested split rules.

---

### Open TODOs
* [ ] **Invitations — bind email in UI:** when creating an invite, optionally set an invitee email so acceptance must use that address (API already supports optional `email`).
* [ ] **Invitations — pending list on Members page:** show outstanding (unaccepted, unexpired) invitations for the space — token/link, optional bound email, expiry — with revoke/copy actions as needed.
* [ ] **Hide unauthorized UI actions:** users must not see controls they cannot execute (backend already rejects). Example: on Members, users with the Member role still see Deactivate/Reset 2FA/Invite — gate those on Spatie permissions (e.g. `users`) or role so only admins see them.
* [x] **Fix/rework individual & manual split transactions:** Add Expense aligns with backend — `individual` = exactly one participant; `manual` = relative weights with live euro preview (not sum-to-amount). Recurring stays Equal/Proportional only (deferred).
* [ ] **SpaceSwitcher “Add space”:** currently a no-op UI item. Implement create/join flow gated by an env flag for **single-space vs multi-space** mode (default assumption: most users stay on a single space; multi-space is opt-in).
