# Schema reference

Revision: `352fd0e`. Fresh migrations on isolated SQLite `:memory:`; no seed data or fleet database access. This records the resulting schema, including new activation fields and concern soft deletion.

PK = primary key; FK = migrated foreign key. Nullable fields are marked yes. A column can be a logical reference without an enforced foreign key. Diagram boxes abbreviate attributes; this reference lists them all.

## activity_logs

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| log_id | INTEGER | PK 1 | no | - |
| user_id | INTEGER |  FK | yes | users.id; delete SET NULL |
| role | varchar |  | yes | - |
| action | varchar |  | no | - |
| module | varchar |  | no | - |
| affected_record_id | varchar |  | yes | - |
| details | TEXT |  | yes | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |
| barangay_id | INTEGER |  FK | yes | barangays.id; delete SET NULL |
| impersonator_user_id | INTEGER |  | yes | - |
| impersonation_session_id | INTEGER |  | yes | - |
| impersonation_reason | TEXT |  | yes | - |

## barangay_number_sequences

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| barangay_id | INTEGER | PK 1 FK | no | barangays.id; delete CASCADE |
| resource | varchar | PK 2 | no | - |
| last_number | INTEGER |  | no | - |

## barangays

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| id | INTEGER | PK 1 | no | - |
| name | varchar |  | no | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |
| city_id | INTEGER |  FK | yes | cities.id; delete SET NULL |
| boundary | TEXT |  | yes | - |
| boundary_status | varchar |  | yes | - |
| boundary_source | varchar |  | yes | - |
| boundary_verified_at | datetime |  | yes | - |
| boundary_verified_by | INTEGER |  FK | yes | users.id; delete SET NULL |
| pending_boundary | TEXT |  | yes | - |
| pending_boundary_source | varchar |  | yes | - |
| psgc_code | varchar |  | yes | - |
| provisioned_at | datetime |  | yes | - |

## cache

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| key | varchar | PK 1 | no | - |
| value | TEXT |  | no | - |
| expiration | INTEGER |  | no | - |

## cache_locks

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| key | varchar | PK 1 | no | - |
| owner | varchar |  | no | - |
| expiration | INTEGER |  | no | - |

## cannibalization_parts

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| part_id | INTEGER | PK 1 | no | - |
| cannibalization_id | INTEGER |  FK | no | cannibalization_records.cannibalization_id; delete CASCADE |
| part_name | varchar |  | no | - |
| part_number | varchar |  | yes | - |
| quantity | INTEGER |  | no | - |
| unit | varchar |  | no | - |
| part_condition | varchar |  | yes | - |
| installation_details | TEXT |  | yes | - |
| notes | TEXT |  | yes | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |

## cannibalization_records

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| cannibalization_id | INTEGER | PK 1 | no | - |
| record_number | varchar |  | yes | - |
| source_vehicle_id | INTEGER |  | no | - |
| destination_type | varchar |  | no | - |
| destination_vehicle_id | INTEGER |  | yes | - |
| storage_location | varchar |  | yes | - |
| reason | TEXT |  | no | - |
| source_reason | TEXT |  | no | - |
| notes | TEXT |  | yes | - |
| recorded_by | INTEGER |  | no | - |
| performed_by_name | varchar |  | yes | - |
| removed_at | datetime |  | no | - |
| received_by_name | varchar |  | yes | - |
| transferred_at | datetime |  | yes | - |
| evidence_urls | TEXT |  | yes | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |

## cities

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| id | INTEGER | PK 1 | no | - |
| province_id | INTEGER |  FK | no | provinces.id; delete CASCADE |
| code | varchar |  | yes | - |
| name | varchar |  | no | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |
| boundary | TEXT |  | yes | - |

## concern_reports

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| id | INTEGER | PK 1 | no | - |
| concern_type | varchar |  | no | - |
| barangay_name | varchar |  | yes | - |
| description | TEXT |  | no | - |
| reporter_name | varchar |  | yes | - |
| reporter_contact | varchar |  | yes | - |
| status | varchar |  | no | - |
| resolved_by | INTEGER |  FK | yes | users.id; delete SET NULL |
| resolved_at | datetime |  | yes | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |
| province_name | varchar |  | yes | - |
| city_name | varchar |  | yes | - |
| deleted_at | datetime |  | yes | - |

## failed_jobs

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| id | INTEGER | PK 1 | no | - |
| uuid | varchar |  | no | - |
| connection | varchar |  | no | - |
| queue | varchar |  | no | - |
| payload | TEXT |  | no | - |
| exception | TEXT |  | no | - |
| failed_at | datetime |  | no | - |

## fault_categories

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| id | INTEGER | PK 1 | no | - |
| name | varchar |  | no | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |

## hidden_ticket_lists

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| user_id | INTEGER | PK 1 FK | no | users.id; delete CASCADE |
| ticket_id | INTEGER | PK 2 FK | no | maintenance_tickets.ticket_id; delete CASCADE |
| created_at | datetime |  | no | - |

## issue_report_attachments

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| attachment_id | INTEGER | PK 1 | no | - |
| issue_report_id | INTEGER |  FK | no | vehicle_issue_reports.issue_report_id; delete CASCADE |
| file_url | varchar |  | no | - |
| original_name | varchar |  | yes | - |
| uploaded_by | INTEGER |  FK | no | users.id; delete NO ACTION |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |

## job_batches

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| id | varchar | PK 1 | no | - |
| name | varchar |  | no | - |
| total_jobs | INTEGER |  | no | - |
| pending_jobs | INTEGER |  | no | - |
| failed_jobs | INTEGER |  | no | - |
| failed_job_ids | TEXT |  | no | - |
| options | TEXT |  | yes | - |
| cancelled_at | INTEGER |  | yes | - |
| created_at | INTEGER |  | no | - |
| finished_at | INTEGER |  | yes | - |

## jobs

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| id | INTEGER | PK 1 | no | - |
| queue | varchar |  | no | - |
| payload | TEXT |  | no | - |
| attempts | INTEGER |  | no | - |
| reserved_at | INTEGER |  | yes | - |
| available_at | INTEGER |  | no | - |
| created_at | INTEGER |  | no | - |

## maintenance_tickets

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| ticket_id | INTEGER | PK 1 | no | - |
| vehicle_id | INTEGER |  FK | no | vehicles.vehicle_id; delete CASCADE |
| created_by | INTEGER |  FK | no | users.id; delete CASCADE |
| issue_report_id | INTEGER |  FK | yes | vehicle_issue_reports.issue_report_id; delete SET NULL |
| ticket_title | varchar |  | no | - |
| ticket_description | TEXT |  | no | - |
| priority | varchar |  | no | - |
| status | varchar |  | no | - |
| assigned_custodian_id | INTEGER |  FK | yes | users.id; delete SET NULL |
| assigned_at | datetime |  | yes | - |
| inspection_notes | TEXT |  | yes | - |
| inspection_result | varchar |  | yes | - |
| inspected_by | INTEGER |  FK | yes | users.id; delete SET NULL |
| inspected_at | datetime |  | yes | - |
| closed_by | INTEGER |  FK | yes | users.id; delete SET NULL |
| closed_at | datetime |  | yes | - |
| closing_notes | TEXT |  | yes | - |
| archived_at | datetime |  | yes | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |
| returned_to_service | tinyint(1) |  | yes | - |
| recurrence_count | INTEGER |  | no | - |
| fault_category | varchar |  | yes | - |
| down_since | datetime |  | yes | - |
| recurrence_of_ticket_id | INTEGER |  FK | yes | maintenance_tickets.ticket_id; delete SET NULL |
| assigned_mechanic_id | INTEGER |  FK | yes | users.id; delete SET NULL |
| decline_reason | TEXT |  | yes | - |
| barangay_number | INTEGER |  | yes | - |
| correction_reason | TEXT |  | yes | - |
| correction_requested_at | datetime |  | yes | - |
| decision_history | TEXT |  | yes | - |
| decision_revision | INTEGER |  | no | - |

## maintenance_types

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| id | INTEGER | PK 1 | no | - |
| name | varchar |  | no | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |

## managed_files

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| id | varchar | PK 1 | no | - |
| disk | varchar |  | no | - |
| path | varchar |  | no | - |
| directory | varchar |  | no | - |
| barangay_id | INTEGER |  | yes | - |
| uploaded_by | INTEGER |  | yes | - |
| original_url | TEXT |  | yes | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |

## migrations

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| id | INTEGER | PK 1 | no | - |
| migration | varchar |  | no | - |
| batch | INTEGER |  | no | - |

## notifications

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| notification_id | INTEGER | PK 1 | no | - |
| user_id | INTEGER |  FK | no | users.id; delete CASCADE |
| title | varchar |  | no | - |
| message | TEXT |  | no | - |
| type | varchar |  | yes | - |
| ticket_id | INTEGER |  FK | yes | maintenance_tickets.ticket_id; delete SET NULL |
| read_at | datetime |  | yes | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |
| issue_report_id | INTEGER |  FK | yes | vehicle_issue_reports.issue_report_id; delete SET NULL |
| schedule_id | INTEGER |  FK | yes | vehicle_maintenance_schedules.schedule_id; delete SET NULL |
| vehicle_id | INTEGER |  FK | yes | vehicles.vehicle_id; delete SET NULL |

## password_reset_tokens

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| email | varchar | PK 1 | no | - |
| token | varchar |  | no | - |
| created_at | datetime |  | yes | - |

## personal_access_tokens

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| id | INTEGER | PK 1 | no | - |
| tokenable_type | varchar |  | no | - |
| tokenable_id | INTEGER |  | no | - |
| name | TEXT |  | no | - |
| token | varchar |  | no | - |
| abilities | TEXT |  | yes | - |
| last_used_at | datetime |  | yes | - |
| expires_at | datetime |  | yes | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |
| impersonator_user_id | INTEGER |  | yes | - |
| impersonation_reason | TEXT |  | yes | - |

## provinces

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| id | INTEGER | PK 1 | no | - |
| code | varchar |  | yes | - |
| name | varchar |  | no | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |

## registration_settings

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| id | INTEGER | PK 1 | no | - |
| staff_code | varchar |  | no | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |
| barangay_id | INTEGER |  FK | yes | barangays.id; delete SET NULL |

## reported_persons

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| id | INTEGER | PK 1 | no | - |
| name | varchar |  | no | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |

## retired_feature_backups

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| id | INTEGER | PK 1 | no | - |
| source_table | varchar |  | no | - |
| source_id | varchar |  | no | - |
| payload | TEXT |  | no | - |

## sessions

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| id | varchar | PK 1 | no | - |
| user_id | INTEGER |  | yes | - |
| ip_address | varchar |  | yes | - |
| user_agent | TEXT |  | yes | - |
| payload | TEXT |  | no | - |
| last_activity | INTEGER |  | no | - |

## ticket_archive_logs

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| archive_id | INTEGER | PK 1 | no | - |
| ticket_id | INTEGER |  | no | - |
| vehicle_id | INTEGER |  | no | - |
| ticket_title | varchar |  | no | - |
| vehicle_name | varchar |  | no | - |
| plate_number | varchar |  | no | - |
| final_status | varchar |  | no | - |
| maintenance_cost | numeric |  | yes | - |
| full_ticket_snapshot | TEXT |  | no | - |
| archived_by | INTEGER |  FK | yes | users.id; delete SET NULL |
| archived_at | datetime |  | no | - |
| deleted_at | datetime |  | yes | - |
| barangay_number | INTEGER |  | yes | - |
| ticket_number | INTEGER |  | yes | - |

## ticket_sub_issue_donor_parts

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| part_id | INTEGER | PK 1 | no | - |
| sub_issue_id | INTEGER |  FK | no | ticket_sub_issues.sub_issue_id; delete CASCADE |
| part_name | varchar |  | no | - |
| quantity | INTEGER |  | no | - |
| part_condition | varchar |  | yes | - |
| installed_at | date |  | yes | - |
| reason | TEXT |  | yes | - |
| notes | TEXT |  | yes | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |

## ticket_sub_issues

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| sub_issue_id | INTEGER | PK 1 | no | - |
| ticket_id | INTEGER |  FK | no | maintenance_tickets.ticket_id; delete CASCADE |
| issue_report_id | INTEGER |  FK | yes | vehicle_issue_reports.issue_report_id; delete SET NULL |
| created_by | INTEGER |  FK | no | users.id; delete CASCADE |
| title | varchar |  | no | - |
| status | varchar |  | no | - |
| assigned_mechanic_id | INTEGER |  FK | yes | users.id; delete SET NULL |
| maintenance_type | varchar |  | yes | - |
| work_order_notes | TEXT |  | yes | - |
| mechanic_assigned_at | datetime |  | yes | - |
| mechanic_assigned_by | INTEGER |  FK | yes | users.id; delete SET NULL |
| repair_logs | TEXT |  | yes | - |
| parts_used | TEXT |  | yes | - |
| repair_started_at | date |  | yes | - |
| repair_completed_at | date |  | yes | - |
| maintenance_cost | numeric |  | yes | - |
| verification_verdict | varchar |  | yes | - |
| verification_notes | TEXT |  | yes | - |
| verified_by | INTEGER |  FK | yes | users.id; delete SET NULL |
| verified_at | datetime |  | yes | - |
| confirmation_verdict | varchar |  | yes | - |
| confirmation_notes | TEXT |  | yes | - |
| confirmed_by | INTEGER |  FK | yes | users.id; delete SET NULL |
| confirmed_at | datetime |  | yes | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |
| attachment_url | varchar |  | yes | - |
| deferred_reason | TEXT |  | yes | - |
| deferred_by | INTEGER |  | yes | - |
| deferred_at | datetime |  | yes | - |
| deferred_issue_report_id | INTEGER |  | yes | - |
| functional_test | TEXT |  | yes | - |
| test_attested | tinyint(1) |  | yes | - |
| verification_assigned_to | INTEGER |  FK | yes | users.id; delete SET NULL |
| reopened_by | INTEGER |  FK | yes | users.id; delete SET NULL |
| reopened_at | datetime |  | yes | - |
| repair_type | varchar |  | yes | - |
| source_vehicle_id | INTEGER |  FK | yes | vehicles.vehicle_id; delete SET NULL |
| external_vendor | varchar |  | yes | - |
| warranty_until | date |  | yes | - |
| cannibalization_status | varchar |  | yes | - |
| cannibalization_rejection_reason | TEXT |  | yes | - |
| cannibalization_reviewed_by | INTEGER |  | yes | - |
| cannibalization_reviewed_at | datetime |  | yes | - |
| cannibalization_issue_report_id | INTEGER |  | yes | - |
| suggested_mechanic_id | INTEGER |  FK | yes | users.id; delete SET NULL |
| part_missing | varchar |  | yes | - |
| part_needed | varchar |  | yes | - |
| external_reason | varchar |  | yes | - |
| external_work_scope | TEXT |  | yes | - |
| external_shop_contact | varchar |  | yes | - |
| external_estimated_cost | numeric |  | yes | - |
| external_sent_by | varchar |  | yes | - |
| external_contact_person | varchar |  | yes | - |
| part_quantity | INTEGER |  | yes | - |
| part_condition | varchar |  | yes | - |
| cannibal_reason | TEXT |  | yes | - |
| part_installed_at | date |  | yes | - |
| external_sent_at | datetime |  | yes | - |
| external_returned_at | datetime |  | yes | - |
| external_return_notes | TEXT |  | yes | - |
| external_actual_cost | numeric |  | yes | - |
| prior_mechanic_ids | TEXT |  | yes | - |
| external_shop_address | varchar |  | yes | - |
| external_expected_return | date |  | yes | - |
| external_handled_by | INTEGER |  | yes | - |
| external_received_by | INTEGER |  | yes | - |
| attachment_files | TEXT |  | yes | - |
| external_cycles | TEXT |  | yes | - |
| work_history | TEXT |  | yes | - |

## users

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| id | INTEGER | PK 1 | no | - |
| name | varchar |  | no | - |
| email | varchar |  | no | - |
| email_verified_at | datetime |  | yes | - |
| password | varchar |  | no | - |
| role | varchar |  | no | - |
| remember_token | varchar |  | yes | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |
| phone | varchar |  | yes | - |
| address | varchar |  | yes | - |
| photo_url | varchar |  | yes | - |
| is_active | tinyint(1) |  | no | - |
| roles | TEXT |  | yes | - |
| barangay_id | INTEGER |  FK | yes | barangays.id; delete SET NULL |
| city_id | INTEGER |  FK | yes | cities.id; delete SET NULL |
| barangay_name | varchar |  | yes | - |
| approved_at | datetime |  | yes | - |
| can_register_vehicles | tinyint(1) |  | no | - |
| barangay_number | INTEGER |  | yes | - |
| awaiting_staff_code | tinyint(1) |  | no | - |
| staff_code_sent_at | datetime |  | yes | - |

## vehicle_categories

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| category_id | INTEGER | PK 1 | no | - |
| category_name | varchar |  | no | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |
| description | TEXT |  | yes | - |
| domain | varchar |  | no | - |
| default_criticality | varchar |  | no | - |

## vehicle_condition_checks

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| condition_check_id | INTEGER | PK 1 | no | - |
| vehicle_id | INTEGER |  FK | no | vehicles.vehicle_id; delete CASCADE |
| condition_result | varchar |  | no | - |
| observations | TEXT |  | yes | - |
| checked_by | INTEGER |  FK | yes | users.id; delete SET NULL |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |
| resulting_ticket_id | INTEGER |  FK | yes | maintenance_tickets.ticket_id; delete SET NULL |
| barangay_number | INTEGER |  | yes | - |

## vehicle_documents

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| document_id | INTEGER | PK 1 | no | - |
| vehicle_id | INTEGER |  FK | no | vehicles.vehicle_id; delete CASCADE |
| title | varchar |  | no | - |
| category | varchar |  | yes | - |
| file_url | varchar |  | no | - |
| added_by | INTEGER |  FK | no | users.id; delete NO ACTION |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |

## vehicle_histories

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| history_id | INTEGER | PK 1 | no | - |
| vehicle_id | INTEGER |  FK | no | vehicles.vehicle_id; delete CASCADE |
| activity_type | varchar |  | no | - |
| description | TEXT |  | no | - |
| related_table | varchar |  | yes | - |
| related_record_id | varchar |  | yes | - |
| updated_by | INTEGER |  FK | yes | users.id; delete SET NULL |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |
| barangay_number | INTEGER |  | yes | - |

## vehicle_hubs

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| hub_id | INTEGER | PK 1 | no | - |
| hub_key | varchar |  | no | - |
| name | varchar |  | no | - |
| label | varchar |  | yes | - |
| lat | double |  | no | - |
| lng | double |  | no | - |
| match_names | TEXT |  | yes | - |
| is_default | tinyint(1) |  | no | - |
| is_hidden | tinyint(1) |  | no | - |
| created_by | INTEGER |  FK | yes | users.id; delete SET NULL |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |
| barangay_id | INTEGER |  FK | yes | barangays.id; delete SET NULL |

## vehicle_imports

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| id | INTEGER | PK 1 | no | - |
| user_id | INTEGER |  FK | no | users.id; delete CASCADE |
| barangay_id | INTEGER |  | yes | - |
| file_name | varchar |  | no | - |
| stored_path | varchar |  | yes | - |
| status | varchar |  | no | - |
| total_rows | INTEGER |  | no | - |
| valid_rows | INTEGER |  | no | - |
| error_rows | INTEGER |  | no | - |
| warning_rows | INTEGER |  | no | - |
| imported_rows | INTEGER |  | no | - |
| failed_rows | INTEGER |  | no | - |
| findings | TEXT |  | yes | - |
| completed_at | datetime |  | yes | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |

## vehicle_issue_reports

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| issue_report_id | INTEGER | PK 1 | no | - |
| vehicle_id | INTEGER |  FK | no | vehicles.vehicle_id; delete CASCADE |
| issue_type | varchar |  | no | - |
| issue_description | TEXT |  | no | - |
| severity_level | varchar |  | no | - |
| photo_url | varchar |  | yes | - |
| reported_by | INTEGER |  FK | no | users.id; delete NO ACTION |
| status | varchar |  | no | - |
| remarks | TEXT |  | yes | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |
| reported_on_behalf_of | varchar |  | yes | - |
| barangay_number | INTEGER |  | yes | - |

## vehicle_locations

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| location_record_id | INTEGER | PK 1 | no | - |
| vehicle_id | INTEGER |  FK | no | vehicles.vehicle_id; delete CASCADE |
| current_location | varchar |  | no | - |
| address_area | varchar |  | yes | - |
| updated_by | INTEGER |  FK | no | users.id; delete NO ACTION |
| remarks | TEXT |  | yes | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |
| barangay_number | INTEGER |  | yes | - |

## vehicle_maintenance_records

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| maintenance_id | INTEGER | PK 1 | no | - |
| vehicle_id | INTEGER |  FK | no | vehicles.vehicle_id; delete CASCADE |
| issue_report_id | INTEGER |  FK | yes | vehicle_issue_reports.issue_report_id; delete SET NULL |
| maintenance_type | varchar |  | no | - |
| problem_reason | TEXT |  | no | - |
| date_started | date |  | yes | - |
| date_completed | date |  | yes | - |
| maintenance_personnel_id | INTEGER |  FK | yes | users.id; delete NO ACTION |
| action_taken | TEXT |  | yes | - |
| parts_used | TEXT |  | yes | - |
| progress_status | varchar |  | no | - |
| remarks | TEXT |  | yes | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |
| verification_result | varchar |  | yes | - |
| verification_notes | TEXT |  | yes | - |
| verified_by | INTEGER |  FK | yes | users.id; delete SET NULL |
| verified_at | datetime |  | yes | - |
| confirmed_by | INTEGER |  FK | yes | users.id; delete SET NULL |
| confirmed_at | datetime |  | yes | - |
| maintenance_cost | numeric |  | yes | - |
| is_external | tinyint(1) |  | no | - |
| external_vendor | varchar |  | yes | - |
| warranty_until | date |  | yes | - |
| receipt_url | varchar |  | yes | - |
| source_vehicle_id | INTEGER |  FK | yes | vehicles.vehicle_id; delete SET NULL |
| closure_reason | TEXT |  | yes | - |
| performed_by_other | varchar |  | yes | - |
| part_name | varchar |  | yes | - |
| barangay_number | INTEGER |  | yes | - |
| ticket_sub_issue_id | INTEGER |  | yes | - |
| test_attested | tinyint(1) |  | no | - |

## vehicle_maintenance_schedules

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| schedule_id | INTEGER | PK 1 | no | - |
| vehicle_id | INTEGER |  FK | no | vehicles.vehicle_id; delete CASCADE |
| maintenance_type | varchar |  | no | - |
| scheduled_date | date |  | no | - |
| scheduled_time | time |  | yes | - |
| service_location | varchar |  | yes | - |
| notes | TEXT |  | yes | - |
| status | varchar |  | no | - |
| created_by | INTEGER |  FK | no | users.id; delete CASCADE |
| assigned_to | INTEGER |  FK | yes | users.id; delete SET NULL |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |
| recurrence_months | INTEGER |  | yes | - |
| resulting_maintenance_id | INTEGER |  FK | yes | vehicle_maintenance_records.maintenance_id; delete SET NULL |
| resulting_ticket_id | INTEGER |  FK | yes | maintenance_tickets.ticket_id; delete SET NULL |
| due_notified_at | datetime |  | yes | - |
| decline_reason | TEXT |  | yes | - |
| barangay_number | INTEGER |  | yes | - |

## vehicle_readiness_checks

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| readiness_check_id | INTEGER | PK 1 | no | - |
| vehicle_id | INTEGER |  FK | no | vehicles.vehicle_id; delete CASCADE |
| checked_by | INTEGER |  FK | yes | users.id; delete SET NULL |
| checklist | TEXT |  | no | - |
| all_passed | tinyint(1) |  | no | - |
| notes | TEXT |  | yes | - |
| checked_at | datetime |  | no | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |

## vehicle_type_fields

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| field_id | INTEGER | PK 1 | no | - |
| category_id | INTEGER |  FK | no | vehicle_categories.category_id; delete CASCADE |
| key | varchar |  | no | - |
| label | varchar |  | no | - |
| field_type | varchar |  | no | - |
| is_required | tinyint(1) |  | no | - |
| unit | varchar |  | yes | - |
| options | TEXT |  | yes | - |
| sort_order | INTEGER |  | no | - |
| is_active | tinyint(1) |  | no | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |

## vehicles

| Column | SQLite type | Key | Nullable | Reference |
|---|---|---|---|---|
| vehicle_id | INTEGER | PK 1 | no | - |
| vehicle_name | varchar |  | no | - |
| plate_number | varchar |  | no | - |
| category_id | INTEGER |  FK | no | vehicle_categories.category_id; delete NO ACTION |
| brand | varchar |  | no | - |
| model | varchar |  | no | - |
| year_model | INTEGER |  | no | - |
| capacity | varchar |  | no | - |
| vehicle_color | varchar |  | no | - |
| current_location | varchar |  | no | - |
| remarks | TEXT |  | yes | - |
| status | varchar |  | no | - |
| condition | varchar |  | no | - |
| created_at | datetime |  | yes | - |
| updated_at | datetime |  | yes | - |
| fuel_type | varchar |  | yes | - |
| photo_url | varchar |  | yes | - |
| hull_material | varchar |  | yes | - |
| engine_type | varchar |  | yes | - |
| archived_at | datetime |  | yes | - |
| archived_by | INTEGER |  FK | yes | users.id; delete SET NULL |
| estimated_return_date | date |  | yes | - |
| decommission_reason | TEXT |  | yes | - |
| decommissioned_by | INTEGER |  | yes | - |
| decommissioned_at | datetime |  | yes | - |
| acquisition_cost | numeric |  | yes | - |
| barangay_id | INTEGER |  FK | yes | barangays.id; delete SET NULL |
| custom_values | TEXT |  | yes | - |
| criticality | varchar |  | yes | - |
| barangay_number | INTEGER |  | yes | - |
| readiness_invalidated_at | datetime |  | yes | - |
