# Ticketech ERD

This ERD lists every column in the 23 Ticketech and Laravel support tables. `PK` marks primary keys and `FK` marks foreign keys. Column types are simplified for readability.

```mermaid
erDiagram
    USERS {
        bigint id PK
        varchar name
        varchar nickname
        varchar email
        varchar profile_photo_path
        timestamp email_verified_at
        varchar password
        varchar remember_token
        timestamp created_at
        timestamp updated_at
        varchar role
        bigint department_id FK
        varchar employment_status
        varchar staff_approval_status
    }
    DEPARTMENTS {
        bigint id PK
        varchar name
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }
    CATEGORIES {
        bigint id PK
        varchar name
        varchar description
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }
    TICKETS {
        bigint id PK
        varchar ticket_number
        bigint requester_user_id FK
        bigint assigned_to_user_id FK
        varchar requester_name
        varchar student_id
        varchar requester_email
        varchar requester_phone
        varchar subject
        text description
        varchar priority
        varchar status
        timestamp assigned_at
        timestamp first_assigned_at
        timestamp resolved_at
        timestamp last_resolved_at
        timestamp created_at
        timestamp updated_at
        bigint department_id FK
        bigint category_id FK
        timestamp resolution_warning_at
        timestamp resolution_due_at
        int sla_paused_minutes
        timestamp sla_paused_at
        timestamp requester_email_verified_at
        varchar requester_email_verification_token_hash
        timestamp requester_email_verification_expires_at
    }
    TICKET_COMMENTS {
        bigint id PK
        bigint ticket_id FK
        bigint author_id FK
        varchar author_name
        text body
        boolean is_internal
        timestamp created_at
        timestamp updated_at
    }
    TICKET_ATTACHMENTS {
        bigint id PK
        bigint ticket_id FK
        bigint comment_id FK
        bigint uploaded_by FK
        varchar original_name
        varchar disk
        varchar path
        varchar mime_type
        bigint size_bytes
        timestamp created_at
        timestamp updated_at
    }
    KNOWLEDGE_ARTICLES {
        bigint id PK
        bigint author_id FK
        varchar title
        varchar slug
        varchar category
        text summary
        longtext body
        boolean is_published
        timestamp published_at
        timestamp created_at
        timestamp updated_at
    }
    KNOWLEDGE_ARTICLE_IMAGES {
        bigint id PK
        bigint knowledge_article_id FK
        varchar path
        timestamp created_at
        timestamp updated_at
    }
    KNOWLEDGE_ARTICLE_REVIEWS {
        bigint id PK
        bigint knowledge_article_id FK
        tinyint rating
        timestamp created_at
        timestamp updated_at
    }
    WORKSPACE_NOTIFICATIONS {
        bigint id PK
        bigint user_id FK
        bigint ticket_id FK
        bigint actor_id FK
        varchar type
        varchar title
        text message
        timestamp read_at
        timestamp created_at
        timestamp updated_at
    }
    TICKET_EVENTS {
        bigint id PK
        bigint ticket_id FK
        bigint actor_id FK
        varchar actor_name
        varchar event
        text description
        varchar request_method
        varchar route_name
        varchar subject_type
        varchar subject_id
        varchar subject_name
        varchar ip_address
        text user_agent
        json properties
        timestamp created_at
        timestamp updated_at
    }
    TICKET_MISUSE_REPORTS {
        bigint id PK
        bigint ticket_id FK
        bigint reporter_id FK
        varchar reason
        text details
        varchar status
        varchar action
        bigint reviewed_by_id FK
        timestamp reviewed_at
        timestamp created_at
        timestamp updated_at
    }
    CLIENT_MODERATIONS {
        bigint id PK
        varchar email
        varchar status
        text reason
        bigint source_report_id FK
        bigint acted_by_id FK
        timestamp cleared_at
        timestamp created_at
        timestamp updated_at
    }
    PENDING_TICKET_SUBMISSIONS {
        bigint id PK
        bigint requester_user_id FK
        varchar requester_name
        varchar requester_email
        json payload
        json attachments
        varchar verification_token_hash
        timestamp expires_at
        timestamp created_at
        timestamp updated_at
        varchar completed_ticket_number
    }
    SYSTEM_SETTINGS {
        varchar setting PK
        text value
        timestamp created_at
        timestamp updated_at
    }
    SESSIONS {
        varchar id PK
        bigint user_id
        varchar ip_address
        text user_agent
        longtext payload
        int last_activity
    }
    PASSWORD_RESET_TOKENS {
        varchar email PK
        varchar token
        timestamp created_at
    }
    CACHE {
        varchar key PK
        mediumtext value
        bigint expiration
    }
    CACHE_LOCKS {
        varchar key PK
        varchar owner
        bigint expiration
    }
    JOBS {
        bigint id PK
        varchar queue
        longtext payload
        smallint attempts
        int reserved_at
        int available_at
        int created_at
    }
    JOB_BATCHES {
        varchar id PK
        varchar name
        int total_jobs
        int pending_jobs
        int failed_jobs
        longtext failed_job_ids
        mediumtext options
        int cancelled_at
        int created_at
        int finished_at
    }
    FAILED_JOBS {
        bigint id PK
        varchar uuid
        varchar connection
        varchar queue
        longtext payload
        longtext exception
        timestamp failed_at
    }
    MIGRATIONS {
        int id PK
        varchar migration
        int batch
    }

    DEPARTMENTS o|--o{ USERS : has
    DEPARTMENTS o|--o{ TICKETS : receives
    CATEGORIES o|--o{ TICKETS : classifies
    USERS o|--o{ TICKETS : requests
    USERS o|--o{ TICKETS : assigned_to
    TICKETS ||--o{ TICKET_COMMENTS : has
    USERS o|--o{ TICKET_COMMENTS : authors
    TICKETS ||--o{ TICKET_ATTACHMENTS : has
    TICKET_COMMENTS o|--o{ TICKET_ATTACHMENTS : includes
    USERS o|--o{ TICKET_ATTACHMENTS : uploads
    USERS o|--o{ KNOWLEDGE_ARTICLES : authors
    KNOWLEDGE_ARTICLES ||--o{ KNOWLEDGE_ARTICLE_IMAGES : has
    KNOWLEDGE_ARTICLES ||--o{ KNOWLEDGE_ARTICLE_REVIEWS : receives
    USERS ||--o{ WORKSPACE_NOTIFICATIONS : receives
    USERS o|--o{ WORKSPACE_NOTIFICATIONS : triggers
    TICKETS o|--o{ WORKSPACE_NOTIFICATIONS : relates_to
    TICKETS o|--o{ TICKET_EVENTS : records
    USERS o|--o{ TICKET_EVENTS : triggers
    TICKETS ||--o{ TICKET_MISUSE_REPORTS : receives
    USERS ||--o{ TICKET_MISUSE_REPORTS : reports
    USERS o|--o{ TICKET_MISUSE_REPORTS : reviews
    TICKET_MISUSE_REPORTS o|--o{ CLIENT_MODERATIONS : informs
    USERS o|--o{ CLIENT_MODERATIONS : acts
    USERS o|--o{ PENDING_TICKET_SUBMISSIONS : submits
```

`SESSIONS.user_id` is indexed but is not declared as a foreign key. The remaining support tables do not have foreign-key relationships in the migrations.

## 2.9.2 Process Modeling

The following DFDs show the requester ticket-submission and helpdesk workflow. Mermaid flowchart shapes represent external entities, processes, and data stores; arrows are labeled with the data exchanged.

### Level 0: Context Diagram

```mermaid
flowchart LR
    requester[Requester]
    staff[Helpdesk Staff]
    admin[Administrator]
    email[Email Service]
    system(("0. Ticketech Helpdesk System"))

    requester -->|Ticket details, attachments, lookup and replies| system
    system -->|Verification request, ticket number, status and replies| requester
    staff -->|Assignment, status and priority changes, replies and notes| system
    system -->|Ticket queue, ticket details and notifications| staff
    admin -->|Departments, categories, moderation and system settings| system
    system -->|Reports, audit information and ticket summaries| admin
    system -->|Verification and requester-update emails| email
```

The context diagram treats Ticketech as one process. Requesters submit and track tickets, helpdesk staff manage and respond to them, and administrators maintain service configuration and review operational information. Ticketech sends verification and ticket-update emails through the external email service.

### Level 1: Ticket Handling

```mermaid
flowchart LR
    requester[Requester]
    staff[Helpdesk Staff]
    admin[Administrator]
    email[Email Service]

    p1(("1.0<br/>Capture Request"))
    p2(("2.0<br/>Verify and Register Ticket"))
    p3(("3.0<br/>Process Ticket"))
    p4(("4.0<br/>Track Ticket and Reply"))
    p5(("5.0<br/>Maintain Helpdesk"))

    d1[(D1 Pending Ticket Submissions)]
    d2[(D2 Tickets)]
    d3[(D3 Ticket Comments)]
    d4[(D4 Ticket Events)]
    d5[(D5 Ticket Attachments and Private Files)]
    d6[(D6 Workspace Notifications)]
    d7[(D7 Departments, Categories and System Settings)]
    d8[(D8 Client Moderations)]

    requester -->|Request details and attachments| p1
    d7 -->|Submission availability and categories| p1
    d8 -->|Existing moderation status| p1
    p1 -->|Pending request and attachment references| d1
    p1 -->|Stored uploaded files| d5
    p1 -->|Email verification request| email
    email -->|Verification link| requester

    requester -->|Verified submission link| p2
    d1 -->|Pending request data| p2
    d8 -->|Current moderation status| p2
    p2 -->|Completion status| d1
    p2 -->|Verified ticket with initial status and priority| d2
    p2 -->|Ticket-created event| d4
    p2 -->|Attachment metadata| d5
    p2 -->|New-ticket notification| d6
    p2 -->|Ticket confirmation and number| requester
    p2 -->|Ticket confirmation email| email

    staff -->|Assignment, transfer, status and priority changes| p3
    staff -->|Public replies and internal notes| p3
    d2 -->|Ticket and queue data| p3
    d7 -->|Department and category data| p3
    p3 -->|Ticket updates| d2
    p3 -->|Comments and notes| d3
    p3 -->|Assignment, status and comment events| d4
    p3 -->|Staff notifications| d6
    p3 -->|Requester status and public-reply emails| email
    p3 -->|Ticket queue and updated details| staff

    requester -->|Ticket number, email and reply| p4
    d2 -->|Ticket status and details| p4
    d3 -->|Public conversation| p4
    d5 -->|Attachment references| p4
    p4 -->|Requester reply| d3
    p4 -->|Reply event| d4
    p4 -->|Reply attachments| d5
    p4 -->|Requester-reply notification| d6
    p4 -->|Ticket details and conversation| requester

    admin -->|Configuration changes and review requests| p5
    d2 -->|Ticket records| p5
    d4 -->|Ticket history| p5
    p5 -->|Departments, categories and system settings| d7
    p5 -->|Moderation decisions| d8
    p5 -->|Reports and audit information| admin
```

Process 1 validates a requester-submitted form against the configured service availability and moderation status, stores it as pending with its uploaded files, and sends an email verification request. Process 2 validates the verified submission, rechecks moderation, creates a new ticket, records its initial event and notifications, and returns a ticket number and confirmation.

Process 3 lets authorized helpdesk staff work the ticket: assign or transfer it, update its status or priority, and add public replies or internal notes. The system records changes, notifies workspace users, and emails requester-facing updates. Process 4 lets a requester retrieve a ticket using its ticket number and email, view its public conversation and attachments, and reply while the ticket is open. Process 5 lets administrators maintain departments, categories, service settings, and client moderation records, and review reports and audit information.
