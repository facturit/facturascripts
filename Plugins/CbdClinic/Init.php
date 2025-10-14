<?php
namespace FacturaScripts\Plugins\CbdClinic;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Template\InitClass;

class Init extends InitClass
{
    public function init(): void
    {
        // Nothing to do on every request for now.
    }

    public function uninstall(): void
    {
        // Plugin tables are not removed automatically to avoid losing clinical data.
    }

    public function update(): void
    {
        $db = new DataBase();
        $db->connect();

        $this->createPacientesTable($db);
        $this->createDolenciasTable($db);
        $this->createPlanesTable($db);
        $this->createVisitasTable($db);
        $this->createConsentimientosTable($db);
    }

    private function createPacientesTable(DataBase $db): void
    {
        $table = 'fs_med_pacientes';
        if (false === $db->tableExists($table)) {
            $db->exec("CREATE TABLE {$table} (
                idpaciente INT AUTO_INCREMENT PRIMARY KEY,
                idcliente INT NULL,
                codcliente VARCHAR(12) NULL,
                codigo_paciente VARCHAR(20) NOT NULL,
                estado VARCHAR(20) NOT NULL DEFAULT 'Activo',
                fecha_alta DATE NOT NULL,
                profesional_asignado VARCHAR(50) NULL,
                notas_internas TEXT NULL,
                banderas_alerta TEXT NULL,
                nombre VARCHAR(100) NOT NULL,
                apellidos VARCHAR(150) NULL,
                nif VARCHAR(15) NULL,
                fecha_nacimiento DATE NULL,
                sexo VARCHAR(10) NULL,
                telefono VARCHAR(25) NULL,
                telefono_secundario VARCHAR(25) NULL,
                email VARCHAR(120) NULL,
                direccion VARCHAR(200) NULL,
                cp VARCHAR(10) NULL,
                ciudad VARCHAR(100) NULL,
                provincia VARCHAR(100) NULL,
                pais VARCHAR(100) NULL,
                metodo_contacto_preferido VARCHAR(20) NULL,
                privacidad_no_marketing TINYINT(1) NOT NULL DEFAULT 0,
                privacidad_solo_seguimiento TINYINT(1) NOT NULL DEFAULT 0,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_codigo_paciente (codigo_paciente),
                KEY idx_codcliente (codcliente),
                KEY idx_profesional (profesional_asignado)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        $this->ensureColumn($db, $table, 'telefono_secundario', "ALTER TABLE {$table} ADD COLUMN telefono_secundario VARCHAR(25) NULL AFTER telefono");
        $this->ensureColumn($db, $table, 'privacidad_solo_seguimiento', "ALTER TABLE {$table} ADD COLUMN privacidad_solo_seguimiento TINYINT(1) NOT NULL DEFAULT 0 AFTER privacidad_no_marketing");
    }

    private function createDolenciasTable(DataBase $db): void
    {
        $table = 'fs_med_dolencias';
        if (false === $db->tableExists($table)) {
            $db->exec("CREATE TABLE {$table} (
                id_dolencia INT AUTO_INCREMENT PRIMARY KEY,
                idpaciente INT NOT NULL,
                tipo VARCHAR(100) NOT NULL,
                intensidad TINYINT UNSIGNED NOT NULL DEFAULT 0,
                frecuencia VARCHAR(20) NULL,
                fecha_inicio_aprox DATE NULL,
                observaciones TEXT NULL,
                alergias TEXT NULL,
                medicacion_actual TEXT NULL,
                contraindicaciones TEXT NULL,
                objetivos TEXT NULL,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_dolencia_paciente FOREIGN KEY (idpaciente) REFERENCES fs_med_pacientes(idpaciente) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        $this->ensureIndex($db, $table, 'idx_dolencia_tipo', "ALTER TABLE {$table} ADD INDEX idx_dolencia_tipo (tipo)");
    }

    private function createPlanesTable(DataBase $db): void
    {
        $table = 'fs_med_planes_cbd';
        if (false === $db->tableExists($table)) {
            $db->exec("CREATE TABLE {$table} (
                id_plan INT AUTO_INCREMENT PRIMARY KEY,
                idpaciente INT NOT NULL,
                estado_plan VARCHAR(20) NOT NULL DEFAULT 'En prueba',
                fecha_inicio DATE NOT NULL,
                fecha_fin DATE NULL,
                codarticulo VARCHAR(30) NULL,
                via_administracion VARCHAR(30) NULL,
                dosificacion VARCHAR(60) NULL,
                frecuencia VARCHAR(20) NULL,
                instrucciones TEXT NULL,
                efectividad_percibida TINYINT UNSIGNED NULL,
                efectos_adversos TEXT NULL,
                notas_seguimiento TEXT NULL,
                lote VARCHAR(40) NULL,
                espectro VARCHAR(30) NULL,
                porcentaje_cbd DECIMAL(5,2) NULL,
                porcentaje_cbg DECIMAL(5,2) NULL,
                thc_declarado DECIMAL(5,2) NULL,
                url_coa VARCHAR(255) NULL,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_plan_paciente FOREIGN KEY (idpaciente) REFERENCES fs_med_pacientes(idpaciente) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        $this->ensureIndex($db, $table, 'idx_plan_estado', "ALTER TABLE {$table} ADD INDEX idx_plan_estado (estado_plan)");
    }

    private function createVisitasTable(DataBase $db): void
    {
        $table = 'fs_med_visitas';
        if (false === $db->tableExists($table)) {
            $db->exec("CREATE TABLE {$table} (
                id_visita INT AUTO_INCREMENT PRIMARY KEY,
                idpaciente INT NOT NULL,
                fecha_hora DATETIME NOT NULL,
                tipo_visita VARCHAR(20) NOT NULL,
                canal VARCHAR(20) NULL,
                profesional VARCHAR(50) NULL,
                id_dolencia INT NULL,
                escala_dolor TINYINT UNSIGNED NULL,
                escala_ansiedad TINYINT UNSIGNED NULL,
                escala_sueno TINYINT UNSIGNED NULL,
                peso DECIMAL(6,2) NULL,
                imc DECIMAL(4,1) NULL,
                notas_visita TEXT NULL,
                ajustes_plan JSON NULL,
                recomendaciones TEXT NULL,
                adjuntos TEXT NULL,
                proxima_cita DATETIME NULL,
                recordatorios_enviados TINYINT(1) NOT NULL DEFAULT 0,
                id_plan INT NULL,
                venta_origen VARCHAR(40) NULL,
                lote_utilizado VARCHAR(40) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_visita_paciente FOREIGN KEY (idpaciente) REFERENCES fs_med_pacientes(idpaciente) ON DELETE CASCADE,
                CONSTRAINT fk_visita_dolencia FOREIGN KEY (id_dolencia) REFERENCES fs_med_dolencias(id_dolencia) ON DELETE SET NULL,
                CONSTRAINT fk_visita_plan FOREIGN KEY (id_plan) REFERENCES fs_med_planes_cbd(id_plan) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        $this->ensureIndex($db, $table, 'idx_visita_fecha', "ALTER TABLE {$table} ADD INDEX idx_visita_fecha (fecha_hora)");
        $this->ensureIndex($db, $table, 'idx_visita_tipo', "ALTER TABLE {$table} ADD INDEX idx_visita_tipo (tipo_visita)");
    }

    private function createConsentimientosTable(DataBase $db): void
    {
        $table = 'fs_med_consentimientos';
        if (false === $db->tableExists($table)) {
            $db->exec("CREATE TABLE {$table} (
                id_consentimiento INT AUTO_INCREMENT PRIMARY KEY,
                idpaciente INT NOT NULL,
                tipo VARCHAR(40) NOT NULL,
                fecha_otorgado DATETIME NOT NULL,
                medio VARCHAR(40) NULL,
                documento VARCHAR(120) NULL,
                revocado TINYINT(1) NOT NULL DEFAULT 0,
                fecha_revocacion DATETIME NULL,
                observaciones TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_consentimiento_paciente FOREIGN KEY (idpaciente) REFERENCES fs_med_pacientes(idpaciente) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        $this->ensureIndex($db, $table, 'idx_consentimiento_tipo', "ALTER TABLE {$table} ADD INDEX idx_consentimiento_tipo (tipo)");
    }

    private function ensureColumn(DataBase $db, string $table, string $column, string $sql): void
    {
        if ($this->columnExists($db, $table, $column)) {
            return;
        }

        $db->exec($sql);
    }

    private function ensureIndex(DataBase $db, string $table, string $index, string $sql): void
    {
        if ($this->indexExists($db, $table, $index)) {
            return;
        }

        $db->exec($sql);
    }

    private function columnExists(DataBase $db, string $table, string $column): bool
    {
        $tableName = $db->escapeColumn($table);
        $columnName = $db->escapeString($column);
        $result = $db->select('SHOW COLUMNS FROM ' . $tableName . " LIKE '" . $columnName . "'");
        return !empty($result);
    }

    private function indexExists(DataBase $db, string $table, string $index): bool
    {
        $tableName = $db->escapeColumn($table);
        $indexName = $db->escapeString($index);
        $result = $db->select('SHOW INDEX FROM ' . $tableName . " WHERE Key_name = '" . $indexName . "'");
        return !empty($result);
    }
}
