Here is the complete API Specification for the Proportional Ledger Engine, outputted entirely in a single Markdown block for easy copying:

````markdown
# API Specification: Proportional Ledger Engine

### Global Conventions
*   **Currency:** All monetary amounts are handled as integers representing the smallest unit (e.g., cents). For example, $60.00 is sent and received as `6000`.
*   **Authentication:** Bearer Token via Laravel Sanctum.
*   **Base URL:** `/api/ledgers/{ledger_id}` (All endpoints are scoped to a specific Space/Ledger).
*   **Enums:**
    *   `split_rule`: "proportional" | "equal" | "individual".
    *   `transaction_type`: "manual" | "recurring" | "settlement" | "reversal".

---

### 0. Authentication

#### 0A. Register
Creates a user and returns a bearer token.

**POST** `/api/auth/register`

**Request Body:**
```json
{
  "name": "Alice Example",
  "email": "alice@example.com",
  "password": "password123"
}
```

**Response (201 Created):**
```json
{
  "user": {
    "id": 1,
    "name": "Alice Example",
    "email": "alice@example.com"
  },
  "token": "1|sanctum-token-value"
}
```

#### 0B. Login
Authenticates a user and returns a new bearer token.

**POST** `/api/auth/login`

**Request Body:**
```json
{
  "email": "alice@example.com",
  "password": "password123"
}
```

**Response (200 OK):**
```json
{
  "user": {
    "id": 1,
    "name": "Alice Example",
    "email": "alice@example.com"
  },
  "token": "2|sanctum-token-value"
}
```

#### 0C. Current User
Returns the currently authenticated user.

**GET** `/api/auth/me`

**Response (200 OK):**
```json
{
  "user": {
    "id": 1,
    "name": "Alice Example",
    "email": "alice@example.com"
  }
}
```

#### 0D. Logout
Revokes the current bearer token.

**POST** `/api/auth/logout`

**Response (200 OK):**
```json
{
  "message": "Logged out successfully."
}
```

---

### 1. Transactions (The Core Ledger)

#### 1A. Create a Manual Transaction
Logs a new expense and triggers the backend to generate immutable double-entry postings. *(Note: The backend generates the double-entry postings automatically; the frontend does not need to handle the debit/credit math)*.

**POST** `/api/ledgers/{ledger_id}/transactions`

**Request Body:**
```json
{
  "payer_account_id": 104,
  "amount": 6000,
  "description": "Pizza for movie night",
  "date": "2026-03-09",
  "type": "manual",
  "split_rule": "equal",
  "participants":
}
```

**Response (201 Created):**
```json
{
  "data": {
    "id": 992,
    "ledger_id": 12,
    "type": "manual",
    "split_rule": "equal",
    "participants":,
    "description": "Pizza for movie night",
    "date": "2026-03-09",
    "created_at": "2026-03-09T22:12:53Z"
  }
}
```

---

### 2. Ledger Members

#### 2A. List Ledger Members
Returns all users attached to the ledger, each with their `shareable_income` from their active financial profile on the given date. Used by the Add Expense modal for proportional split calculations.

**GET** `/api/ledgers/{ledger_id}/users?date=2026-03-17`

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| date | string (Y-m-d) | No | Date for shareable_income resolution. Defaults to today. |

**Response (200 OK):**
```json
{
  "data": [
    { "id": 1, "name": "Alice", "shareable_income": 60000 },
    { "id": 2, "name": "Bob", "shareable_income": 40000 }
  ]
}
```

---

### 3. Financial Profiles (Dynamic Income/Deductions)

#### 3A. Update Active Financial Profile
This is an idempotent endpoint to update a user's current financial capacity. The backend handles the bi-temporal logic (branching versus in-place editing) based on settlement locks.

**PUT** `/api/ledgers/{ledger_id}/users/{user_id}/financial-profile/active`

**Request Body:**
```json
{
  "incomes": [
    { "description": "Base Salary", "amount": 400000 },
    { "description": "Freelance", "amount": 50000 }
  ],
  "deductions": [
    { "description": "Health Insurance", "amount": 15000 },
    { "description": "Student Loan", "amount": 20000 }
  ]
}
```

**Response (200 OK):**
```json
{
  "data": {
    "id": 105,
    "ledger_id": 12,
    "user_id": 1,
    "valid_from": "2026-03-01",
    "valid_to": null,
    "incomes": [
      { "description": "Base Salary", "amount": 400000 },
      { "description": "Freelance", "amount": 50000 }
    ],
    "deductions": [
      { "description": "Health Insurance", "amount": 15000 },
      { "description": "Student Loan", "amount": 20000 }
    ],
    "computed": {
      "total_income": 450000,
      "total_deductions": 35000,
      "shareable_income": 415000
    }
  }
}
```

---

### 4. Recurring Expenses (Blueprints)

#### 4A. Create Recurring Blueprint
Creates a rule for the 1st-of-the-month backend cron job to materialize into the ledger.

**POST** `/api/ledgers/{ledger_id}/recurring-transactions`

**Request Body:**
```json
{
  "payer_account_id": 999,
  "amount": 120000,
  "description": "Monthly Rent",
  "split_rule": "proportional",
  "participants":,
  "start_date": "2026-04-01",
  "frequency": "monthly"
}
```

**Response (201 Created):**
```json
{
  "data": {
    "id": 45,
    "payer_account_id": 999,
    "amount": 120000,
    "description": "Monthly Rent",
    "split_rule": "proportional",
    "participants":,
    "frequency": "monthly",
    "valid_from": "2026-04-01",
    "valid_to": null,
    "is_active": true
  }
}
```

---

### 5. End-of-Month Settlements

#### 5A. Preview Settlement
Calculates the expected versus actual contributions without locking the database, which is used to populate the "Settle Up" dashboard.

**GET** `/api/ledgers/{ledger_id}/settlements/preview?date=2026-03-31`

**Response (200 OK):**
```json
{
  "data": {
    "period_start": "2026-03-01",
    "period_end": "2026-03-31",
    "settlement_mode": "joint_clearinghouse",
    "summary": {
      "total_shared_spend": 120000,
      "pool_base_budget": 300000,
      "pool_current_balance": 240000
    },
    "user_breakdowns": [
      {
        "user_id": 1,
        "name": "Bob",
        "active_ratio": 0.60,
        "target_liability": 72000,
        "paid_out_of_pocket": 5000,
        "net_balance": -67000
      }
    ],
    "required_transfers": [
      {
        "from_account_id": 104,
        "to_account_id": 999,
        "amount": 67000,
        "instruction": "Bob needs to transfer €670.00 to the Joint Pool"
      }
    ]
  }
}
```

#### 4B. Execute Settlement
Locks the ledger, writes the transfer postings, and sets the temporal boundary for financial profiles.

**POST** `/api/ledgers/{ledger_id}/settlements`

**Request Body:**
```json
{
  "period_end": "2026-03-31"
}
```

**Response:** 201 Created (No body required)

---

### 5. AI Bank Statement Ingestion (BYOK)

#### 5A. Upload CSV
Dispatches an asynchronous backend job to process the statement via LLM APIs.

**POST** `/api/ledgers/{ledger_id}/ingestion/upload` *(Content-Type: multipart/form-data)*
*   **file:** (Binary CSV file)
*   **target_account_id:** 104

**Response (202 Accepted):**
```json
{
  "message": "Bank statement queued for processing.",
  "job_id": "uuid-9876-5432-1098"
}
```

#### 5B. Fetch Pending AI Transactions
Polls the staging table for LLM-categorized results awaiting human review.

**GET** `/api/ledgers/{ledger_id}/ingestion/pending`

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 801,
      "date": "2026-03-02",
      "raw_description": "UBER *EATS AMSTERDAM",
      "suggested_description": "Uber Eats",
      "suggested_amount": 3450,
      "suggested_split_rule": "equal",
      "suggested_participants":,
      "status": "pending"
    }
  ]
}
```

#### 5C. Approve Pending Transactions
Submits the human-verified transactions to be permanently written to the ledger.

**POST** `/api/ledgers/{ledger_id}/ingestion/approve`

**Request Body:**
```json
{
  "transactions": [
    {
      "pending_transaction_id": 801,
      "description": "Uber Eats",
      "amount": 3450,
      "split_rule": "equal",
      "participants":
    }
  ]
}
```

**Response:** 201 Created
````