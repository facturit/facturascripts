-- Google Drive Sync plugin schema installation
-- This script mirrors the XML definitions under Plugins/googledrive_sync/Table.

CREATE TABLE IF NOT EXISTS gd_company_cfg (
    id SERIAL PRIMARY KEY,
    idempresa INTEGER NOT NULL,
    credentials_mode CHARACTER VARYING(20) NOT NULL DEFAULT 'service',
    credentials_json TEXT,
    token_json TEXT,
    token_expires_at TIMESTAMP,
    token_updated_at TIMESTAMP,
    shared_drive_id CHARACTER VARYING(64),
    root_folder_id CHARACTER VARYING(128),
    root_folder_path CHARACTER VARYING(255),
    path_template CHARACTER VARYING(255) NOT NULL DEFAULT '{year}/{doctype}/{third.nif} - {third.name}',
    filename_template CHARACTER VARYING(255) NOT NULL DEFAULT '{date:YYYYMMDD}-{doctype}-{doc.serie}-{doc.number}-{third.nif}-{third.name}.pdf',
    upload_pdf BOOLEAN NOT NULL DEFAULT TRUE,
    auto_share BOOLEAN NOT NULL DEFAULT FALSE,
    share_emails TEXT,
    delete_on_remove BOOLEAN NOT NULL DEFAULT FALSE,
    reverse_sync BOOLEAN NOT NULL DEFAULT FALSE,
    anonymize_routes BOOLEAN NOT NULL DEFAULT FALSE,
    excluded_models TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ca_gd_company_cfg_idempresa FOREIGN KEY (idempresa)
        REFERENCES empresas (idempresa) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT gd_company_cfg_unique_company UNIQUE (idempresa)
);

CREATE TABLE IF NOT EXISTS gd_file_map (
    id SERIAL PRIMARY KEY,
    idempresa INTEGER NOT NULL,
    model CHARACTER VARYING(60) NOT NULL,
    iddocument INTEGER NOT NULL,
    google_file_id CHARACTER VARYING(128),
    google_folder_id CHARACTER VARYING(128),
    google_web_link CHARACTER VARYING(255),
    filename CHARACTER VARYING(255),
    content_hash CHARACTER VARYING(128),
    sync_status CHARACTER VARYING(20) NOT NULL DEFAULT 'queued',
    sync_error TEXT,
    last_synced_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ca_gd_file_map_idempresa FOREIGN KEY (idempresa)
        REFERENCES empresas (idempresa) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT gd_file_map_unique_doc UNIQUE (model, iddocument, idempresa)
);
CREATE INDEX IF NOT EXISTS gd_file_map_idx_file ON gd_file_map (google_file_id);
CREATE INDEX IF NOT EXISTS gd_file_map_idx_status ON gd_file_map (sync_status);
CREATE INDEX IF NOT EXISTS gd_file_map_idx_updated ON gd_file_map (updated_at);

CREATE TABLE IF NOT EXISTS gd_folders_map (
    id SERIAL PRIMARY KEY,
    idempresa INTEGER NOT NULL,
    resolved_path CHARACTER VARYING(255) NOT NULL,
    path_hash CHARACTER VARYING(64) NOT NULL,
    folder_id CHARACTER VARYING(128) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ca_gd_folders_map_idempresa FOREIGN KEY (idempresa)
        REFERENCES empresas (idempresa) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT gd_folders_map_unique_path UNIQUE (idempresa, resolved_path)
);
CREATE INDEX IF NOT EXISTS gd_folders_map_idx_folder ON gd_folders_map (folder_id);
CREATE INDEX IF NOT EXISTS gd_folders_map_idx_hash ON gd_folders_map (path_hash);

CREATE TABLE IF NOT EXISTS gd_queue (
    id SERIAL PRIMARY KEY,
    idempresa INTEGER NOT NULL,
    model CHARACTER VARYING(60) NOT NULL,
    iddocument INTEGER NOT NULL,
    action CHARACTER VARYING(20) NOT NULL DEFAULT 'sync',
    state CHARACTER VARYING(20) NOT NULL DEFAULT 'queued',
    priority SMALLINT NOT NULL DEFAULT 5,
    attempts INTEGER NOT NULL DEFAULT 0,
    max_attempts INTEGER NOT NULL DEFAULT 5,
    available_at TIMESTAMP,
    locked_at TIMESTAMP,
    last_error TEXT,
    google_file_id CHARACTER VARYING(128),
    payload TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ca_gd_queue_idempresa FOREIGN KEY (idempresa)
        REFERENCES empresas (idempresa) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE INDEX IF NOT EXISTS gd_queue_idx_doc ON gd_queue (model, iddocument, idempresa);
CREATE INDEX IF NOT EXISTS gd_queue_idx_state ON gd_queue (state, available_at);
CREATE INDEX IF NOT EXISTS gd_queue_idx_priority ON gd_queue (priority, created_at);

CREATE TABLE IF NOT EXISTS gd_log (
    id SERIAL PRIMARY KEY,
    idempresa INTEGER,
    model CHARACTER VARYING(60) NOT NULL,
    iddocument INTEGER NOT NULL,
    action CHARACTER VARYING(30) NOT NULL,
    result CHARACTER VARYING(20) NOT NULL,
    message TEXT,
    google_file_id CHARACTER VARYING(128),
    filesize BIGINT,
    username CHARACTER VARYING(50),
    duration_ms INTEGER,
    metadata TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ca_gd_log_idempresa FOREIGN KEY (idempresa)
        REFERENCES empresas (idempresa) ON DELETE SET NULL ON UPDATE CASCADE
);
CREATE INDEX IF NOT EXISTS gd_log_idx_doc ON gd_log (model, iddocument);
CREATE INDEX IF NOT EXISTS gd_log_idx_created ON gd_log (created_at);
