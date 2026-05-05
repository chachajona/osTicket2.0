DROP TABLE IF EXISTS ost_filter_action;
DROP TABLE IF EXISTS ost_filter_rule;
DROP TABLE IF EXISTS ost_filter;
DROP TABLE IF EXISTS ost_canned_response;
DROP TABLE IF EXISTS ost_help_topic;
DROP TABLE IF EXISTS ost_team_member;
DROP TABLE IF EXISTS ost_team;
DROP TABLE IF EXISTS ost_staff_dept_access;
DROP TABLE IF EXISTS ost_staff;
DROP TABLE IF EXISTS ost_department;
DROP TABLE IF EXISTS ost_email_template;
DROP TABLE IF EXISTS ost_email_template_group;
DROP TABLE IF EXISTS ost_email_account;
DROP TABLE IF EXISTS ost_email;
DROP TABLE IF EXISTS ost_sla;
DROP TABLE IF EXISTS ost_role;

CREATE TABLE ost_role (
    id INTEGER PRIMARY KEY,
    flags INTEGER DEFAULT 0,
    name TEXT NOT NULL,
    permissions TEXT,
    notes TEXT,
    created TEXT,
    updated TEXT
);

INSERT INTO ost_role (id, flags, name, permissions, notes, created, updated) VALUES
    (1, 1, 'Agents', '{"admin.access":true,"ticket.create":true,"ticket.edit":false}', 'Frontline agents', '2026-01-01 10:00:00', '2026-01-01 10:00:00'),
    (2, 1, 'Managers', '{"admin.access":true,"admin.staff.update":true}', 'Managers', '2026-01-01 11:00:00', '2026-01-01 11:00:00');

CREATE TABLE ost_sla (
    id INTEGER PRIMARY KEY,
    schedule_id INTEGER,
    flags INTEGER DEFAULT 0,
    grace_period INTEGER DEFAULT 0,
    name TEXT NOT NULL,
    notes TEXT,
    created TEXT,
    updated TEXT
);

INSERT INTO ost_sla (id, schedule_id, flags, grace_period, name, notes, created, updated) VALUES
    (1, NULL, 1, 4, 'Standard', 'Default SLA', '2026-01-02 09:00:00', '2026-01-02 09:00:00');

CREATE TABLE ost_email (
    email_id INTEGER PRIMARY KEY,
    noautoresp INTEGER DEFAULT 0,
    priority_id INTEGER DEFAULT 0,
    dept_id INTEGER DEFAULT 0,
    topic_id INTEGER DEFAULT 0,
    email TEXT,
    name TEXT,
    notes TEXT,
    created TEXT,
    updated TEXT
);

INSERT INTO ost_email (email_id, noautoresp, priority_id, dept_id, topic_id, email, name, notes, created, updated) VALUES
    (1, 0, 0, 1, 1, 'support@example.test', 'Support Inbox', 'Primary inbox', '2026-01-03 08:00:00', '2026-01-03 08:00:00');

CREATE TABLE ost_email_account (
    id INTEGER PRIMARY KEY,
    email_id INTEGER NOT NULL,
    type TEXT,
    auth_bk TEXT,
    auth_id TEXT,
    active INTEGER DEFAULT 1,
    host TEXT,
    port INTEGER,
    folder TEXT,
    protocol TEXT,
    encryption TEXT,
    created TEXT,
    updated TEXT
);

INSERT INTO ost_email_account (id, email_id, type, auth_bk, auth_id, active, host, port, folder, protocol, encryption, created, updated) VALUES
    (1, 1, 'mailbox', 'secret', 'support-user', 1, 'imap.example.test', 993, 'INBOX', 'IMAP', 'ssl', '2026-01-03 08:05:00', '2026-01-03 08:05:00');

CREATE TABLE ost_email_template_group (
    tpl_id INTEGER PRIMARY KEY,
    isactive INTEGER DEFAULT 1,
    name TEXT,
    lang TEXT,
    notes TEXT,
    created TEXT,
    updated TEXT
);

INSERT INTO ost_email_template_group (tpl_id, isactive, name, lang, notes, created, updated) VALUES
    (1, 1, 'Default Templates', 'en_US', 'Default group', '2026-01-03 09:00:00', '2026-01-03 09:00:00');

CREATE TABLE ost_email_template (
    id INTEGER PRIMARY KEY,
    tpl_id INTEGER,
    code_name TEXT,
    subject TEXT,
    body TEXT,
    notes TEXT,
    created TEXT,
    updated TEXT
);

INSERT INTO ost_email_template (id, tpl_id, code_name, subject, body, notes, created, updated) VALUES
    (1, 1, 'ticket.autoresp', 'We received your ticket', 'Thanks for contacting us.', 'Autoresponder', '2026-01-03 09:05:00', '2026-01-03 09:05:00');

CREATE TABLE ost_department (
    id INTEGER PRIMARY KEY,
    dept_id INTEGER,
    tpl_id INTEGER,
    sla_id INTEGER,
    manager_id INTEGER,
    email_id INTEGER,
    name TEXT,
    signature TEXT,
    ispublic INTEGER DEFAULT 1
);

INSERT INTO ost_department (id, dept_id, tpl_id, sla_id, manager_id, email_id, name, signature, ispublic) VALUES
    (1, NULL, 1, 1, 2, 1, 'Support', 'Support team', 1),
    (2, 1, 1, 1, 2, 1, 'Billing', 'Billing team', 0);

CREATE TABLE ost_staff (
    staff_id INTEGER PRIMARY KEY,
    dept_id INTEGER NOT NULL,
    role_id INTEGER,
    username TEXT,
    firstname TEXT,
    lastname TEXT,
    email TEXT,
    phone TEXT,
    mobile TEXT,
    signature TEXT,
    passwd TEXT,
    isactive INTEGER DEFAULT 1,
    isadmin INTEGER DEFAULT 0,
    isvisible INTEGER DEFAULT 1,
    change_passwd INTEGER DEFAULT 0,
    passwdreset TEXT,
    created TEXT,
    updated TEXT,
    lastlogin TEXT
);

INSERT INTO ost_staff (staff_id, dept_id, role_id, username, firstname, lastname, email, phone, mobile, signature, passwd, isactive, isadmin, isvisible, change_passwd, passwdreset, created, updated, lastlogin) VALUES
    (1, 1, 1, 'alice', 'Alice', 'Agent', 'alice@example.test', '111-1111', NULL, 'Regards, Alice', '$2y$12$legacy', 1, 0, 1, 0, NULL, '2026-01-04 08:00:00', '2026-01-04 08:00:00', NULL),
    (2, 2, 2, 'bob', 'Bob', 'Boss', 'bob@example.test', '222-2222', '333-3333', 'Regards, Bob', '$2y$12$legacy', 1, 1, 1, 0, NULL, '2026-01-04 09:00:00', '2026-01-04 09:00:00', NULL);

CREATE TABLE ost_staff_dept_access (
    staff_id INTEGER NOT NULL,
    dept_id INTEGER NOT NULL,
    role_id INTEGER NOT NULL,
    flags INTEGER DEFAULT 0,
    PRIMARY KEY (staff_id, dept_id)
);

INSERT INTO ost_staff_dept_access (staff_id, dept_id, role_id, flags) VALUES
    (2, 1, 1, 0);

CREATE TABLE ost_team (
    team_id INTEGER PRIMARY KEY,
    lead_id INTEGER,
    flags INTEGER DEFAULT 1,
    name TEXT,
    notes TEXT,
    created TEXT,
    updated TEXT
);

INSERT INTO ost_team (team_id, lead_id, flags, name, notes, created, updated) VALUES
    (1, 2, 1, 'Escalations', 'Escalation team', '2026-01-05 08:00:00', '2026-01-05 08:00:00');

CREATE TABLE ost_team_member (
    team_id INTEGER NOT NULL,
    staff_id INTEGER NOT NULL,
    flags INTEGER DEFAULT 0,
    PRIMARY KEY (team_id, staff_id)
);

INSERT INTO ost_team_member (team_id, staff_id, flags) VALUES
    (1, 1, 0),
    (1, 2, 0);

CREATE TABLE ost_help_topic (
    topic_id INTEGER PRIMARY KEY,
    topic_pid INTEGER DEFAULT 0,
    ispublic INTEGER DEFAULT 1,
    noautoresp INTEGER DEFAULT 0,
    flags INTEGER DEFAULT 0,
    status_id INTEGER DEFAULT 0,
    priority_id INTEGER DEFAULT 0,
    dept_id INTEGER,
    staff_id INTEGER,
    team_id INTEGER,
    sla_id INTEGER,
    form_id INTEGER,
    isactive INTEGER DEFAULT 1,
    disabled INTEGER DEFAULT 0,
    page_id INTEGER DEFAULT 0,
    sequence_id INTEGER DEFAULT 0,
    sort INTEGER DEFAULT 0,
    topic TEXT,
    number_format TEXT,
    notes TEXT,
    created TEXT,
    updated TEXT
);

INSERT INTO ost_help_topic (topic_id, topic_pid, ispublic, noautoresp, flags, status_id, priority_id, dept_id, staff_id, team_id, sla_id, form_id, isactive, disabled, page_id, sequence_id, sort, topic, number_format, notes, created, updated) VALUES
    (1, 0, 1, 0, 0, 0, 0, 1, 1, 1, 1, NULL, 1, 0, 0, 0, 1, 'General Support', 'SUP-%06d', 'General inquiries', '2026-01-05 09:00:00', '2026-01-05 09:00:00');

CREATE TABLE ost_canned_response (
    canned_id INTEGER PRIMARY KEY,
    dept_id INTEGER,
    isenabled INTEGER DEFAULT 1,
    title TEXT,
    response TEXT,
    lang TEXT,
    notes TEXT,
    created TEXT,
    updated TEXT
);

INSERT INTO ost_canned_response (canned_id, dept_id, isenabled, title, response, lang, notes, created, updated) VALUES
    (1, 1, 1, 'Greeting', 'Hello from support', 'en_US', 'Standard greeting', '2026-01-05 09:30:00', '2026-01-05 09:30:00');

CREATE TABLE ost_filter (
    id INTEGER PRIMARY KEY,
    execorder INTEGER DEFAULT 0,
    isactive INTEGER DEFAULT 1,
    flags INTEGER DEFAULT 0,
    status INTEGER DEFAULT 0,
    match_all_rules INTEGER DEFAULT 0,
    stop_onmatch INTEGER DEFAULT 0,
    target TEXT,
    email_id INTEGER,
    name TEXT,
    notes TEXT,
    created TEXT,
    updated TEXT
);

INSERT INTO ost_filter (id, execorder, isactive, flags, status, match_all_rules, stop_onmatch, target, email_id, name, notes, created, updated) VALUES
    (1, 1, 1, 0, 0, 0, 0, 'ticket', 1, 'VIP Routing', 'Route VIP customers', '2026-01-05 10:00:00', '2026-01-05 10:00:00');

CREATE TABLE ost_filter_rule (
    id INTEGER PRIMARY KEY,
    filter_id INTEGER NOT NULL,
    what TEXT,
    how TEXT,
    val TEXT,
    isactive INTEGER DEFAULT 1,
    notes TEXT,
    created TEXT,
    updated TEXT
);

INSERT INTO ost_filter_rule (id, filter_id, what, how, val, isactive, notes, created, updated) VALUES
    (1, 1, 'email', 'equal', 'vip@example.test', 1, 'VIP email', '2026-01-05 10:05:00', '2026-01-05 10:05:00');

CREATE TABLE ost_filter_action (
    id INTEGER PRIMARY KEY,
    filter_id INTEGER NOT NULL,
    sort INTEGER DEFAULT 1,
    type TEXT,
    target TEXT,
    updated TEXT
);

INSERT INTO ost_filter_action (id, filter_id, sort, type, target, updated) VALUES
    (1, 1, 1, 'assign-team', 'team:1', '2026-01-05 10:10:00');
