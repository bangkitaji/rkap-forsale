# COA and Activity-COA Mapping Excel Import Feature Documentation

## Overview

This document describes the Excel import features for Chart of Accounts (COA) and Activity-COA Mapping in the RKAP application. These features allow bulk uploading of COA records and mapping relationships between activities and COAs through Excel files.

## Features

### 1. COA (Chart of Accounts) Import

- **Location**: Master Data → COAs → Import Excel button
- **Template**: `public/templates/coa_template.xlsx`
- **Supported Formats**: XLSX, XLS, CSV
- **Maximum File Size**: 5 MB

#### COA Template Columns

| Column      | Type   | Required | Description                                        |
| ----------- | ------ | -------- | -------------------------------------------------- |
| Code        | String | Yes      | Unique identifier for the COA (max 255 characters) |
| Title       | String | Yes      | Name/title of the COA account (max 255 characters) |
| Description | String | No       | Detailed description of the account                |

#### Features

- Automatic creation of new COAs
- Automatic update of existing COAs (matched by Code)
- Duplicate code validation
- Empty row skipping
- Detailed error reporting

### 2. Activity-COA Mapping Import

- **Location**: Master Data → Activity ↔ COA Mapping → Import Excel button
- **Template**: `public/templates/activity_coa_mapping_template.xlsx`
- **Supported Formats**: XLSX, XLS, CSV
- **Maximum File Size**: 5 MB

#### Activity-COA Mapping Template Columns

| Column        | Type   | Required | Description                  |
| ------------- | ------ | -------- | ---------------------------- |
| Activity Code | String | Yes      | Code of an existing activity |
| COA Code      | String | Yes      | Code of an existing COA      |

#### Features

- Create relationships between activities and COAs
- Automatic validation of both activity and COA codes
- Duplicate mapping prevention (same mapping won't be created twice)
- Empty row skipping
- Detailed error reporting
- Support for one activity linked to multiple COAs

## How to Use

### For COA Import

#### Step 1: Download Template

1. Navigate to Master Data → COAs
2. Click the "Import Excel" button
3. Click "Download template" link in the modal
4. The template file will be downloaded to your computer

#### Step 2: Fill Data

1. Open the downloaded template file in Excel, Google Sheets, or any spreadsheet application
2. Fill in the required columns:
   - **Code**: A unique identifier for the account (e.g., "1-1001", "COA-001")
   - **Title**: The account name (e.g., "Personnel Costs", "Operating Expenses")
   - **Description**: Optional details about the account
3. Do not modify the header row
4. Save the file

#### Step 3: Upload File

1. Click the "Import Excel" button in the COA page
2. Select the filled template file from your computer
3. Click "Import" button
4. Wait for the import to complete

#### Step 4: Review Results

After import completes, a summary message will show:

- Number of records successfully imported
- Number of failed records
- Specific error messages for failed rows (first 10 errors shown)

### For Activity-COA Mapping Import

#### Step 1: Download Template

1. Navigate to Master Data → Activity ↔ COA Mapping
2. Click the "Import Excel" button
3. Click "Download template" link in the modal
4. The template file will be downloaded to your computer

#### Step 2: Fill Data

1. Open the downloaded template file
2. Fill in the columns:
   - **Activity Code**: Code of an existing activity (must already be in the system)
   - **COA Code**: Code of an existing COA (must already be in the system)
3. To link one activity to multiple COAs, create multiple rows with the same activity code
4. Do not modify the header row
5. Save the file

Example:

```
Activity Code | COA Code
ACT001       | COA001
ACT001       | COA002
ACT002       | COA001
```

#### Step 3: Upload File

1. Click the "Import Excel" button in the Activity ↔ COA Mapping page
2. Select the filled template file
3. Click "Import" button
4. Wait for the import to complete

#### Step 4: Review Results

After import completes, a summary message will show:

- Number of mappings successfully created
- Number of failed mappings
- Specific error messages

## File Structure

### Import Classes (app/Imports/)

- `CoaImport.php` - Handles COA data import logic
- `ActivityCoaMappingImport.php` - Handles Activity-COA mapping import logic

### Console Command (app/Console/Commands/)

- `GenerateImportTemplates.php` - Generates Excel template files (updated)

### Templates (public/templates/)

- `coa_template.xlsx` - Download template for COAs
- `activity_coa_mapping_template.xlsx` - Download template for Activity-COA mappings

### Routes (routes/web.php)

- `GET /templates/download/coa` - Download COA template
- `GET /templates/download/activity-coa-mapping` - Download Activity-COA Mapping template

### Livewire Components (app/Livewire/MasterData/)

- `Coas.php` - COAs management with import functionality
- `ActivityCoaMapping.php` - Activity-COA mapping with import functionality

### Views (resources/views/livewire/master-data/)

- `coas.blade.php` - COA UI with upload modal
- `activity-coa-mapping.blade.php` - Activity-COA Mapping UI with upload modal

## Error Handling

### Common Errors and Solutions

#### "File upload failed: File too large"

- **Solution**: Ensure file size is under 5 MB

#### "File upload failed: Invalid file format"

- **Solution**: Use only XLSX, XLS, or CSV files

#### "Row X: Code and Title are required"

- **Solution**: Ensure Code and Title columns have values (for COA import)

#### "Row X: Activity Code and COA Code are required"

- **Solution**: Ensure both Activity Code and COA Code columns have values

#### "Row X: Activity with code 'XXX' not found"

- **Solution**: Verify the activity code exists in the system

#### "Row X: COA with code 'XXX' not found"

- **Solution**: Verify the COA code exists in the system

### Validation Rules

**COAs:**

- Code: Required, string, max 255 characters, unique
- Title: Required, string, max 255 characters
- Description: Optional, any text

**Activity-COA Mappings:**

- Activity Code: Required, must match an existing activity
- COA Code: Required, must match an existing COA
- Duplicates are automatically skipped (no error)

## Technical Details

### Import Process

1. File is uploaded to Livewire component
2. File is validated (format, size)
3. maatwebsite/excel processes the file with heading row handling
4. Each row is validated against rules
5. Valid rows are imported (create, update, or link)
6. Failed rows are collected with error messages
7. Summary is displayed to user

### Key Dependencies

- `maatwebsite/excel` - Excel processing
- `phpoffice/phpspreadsheet` - Spreadsheet manipulation
- `livewire/livewire` - Real-time component updates

## Permissions

COA and Activity-COA Mapping imports require appropriate permissions:

- COA Import: `masterdata.coa.manage`
- Activity-COA Mapping Import: `masterdata.activity.manage`

These permissions are checked in the middleware on the download routes.

## Integration Notes

### Relationship Structure

- An Activity has many COAs through the `activity_coa` pivot table
- A COA has many Activities through the `activity_coa` pivot table
- The ActivityCoaMappingImport uses `syncWithoutDetaching()` to add new mappings without removing existing ones

### Manual vs. Import Approach

Users can choose to:

- **Manually map** through the UI: Select activity, then check/uncheck COAs and save
- **Import in bulk**: Upload an Excel file with pre-defined mappings

Both approaches work together seamlessly.

## Tips and Best Practices

1. **Import order**: Import COAs first, then activities, then activity-COA mappings
2. **Use templates**: Always download and use the provided templates
3. **Validate before import**: Check your data in the spreadsheet before uploading
4. **Test first**: Start with a few rows to ensure the import works
5. **Keep backups**: Save a copy of your import file
6. **One activity, multiple COAs**: Create multiple rows with the same activity code for bulk linking
7. **Preserve relationships**: Importing mappings won't remove existing mappings; use manual UI to modify

## Troubleshooting

### Q: Why did my import fail?

A: Check the error message displayed after import. Look for specific row numbers and validation errors. Ensure all required codes exist in the system.

### Q: Can I update existing COA records?

A: Yes! If a COA with the same code exists, it will be updated with the new title and description.

### Q: What happens if I import a mapping that already exists?

A: The system automatically skips duplicate mappings. No error is reported, but the mapping count won't increase.

### Q: Can one activity have multiple COAs?

A: Yes! Create multiple rows in the Activity-COA Mapping template, each with the same activity code but different COA codes.

### Q: How many rows can I import at once?

A: The system supports importing multiple rows. It's recommended to keep files under 1000 rows for optimal performance.

### Q: Do I need to remove old mappings before importing new ones?

A: No. New mappings are added without removing existing ones. To replace all mappings for an activity, use the manual UI instead.

## Future Enhancements

Potential improvements for these features:

1. Bulk update/replace functionality for Activity-COA mappings
2. Mapping preview before import
3. Error export to Excel for easier correction
4. Import scheduling and automation
5. Activity-COA mapping validation and reporting
6. COA hierarchy/parent-child support

---

**Last Updated**: 2026-06-05
**Version**: 1.0
**Author**: System Administrator
