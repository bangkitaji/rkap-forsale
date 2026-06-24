# Excel Import Feature Documentation

## Overview

This document describes the Excel import feature for Work Plans and Activities in the RKAP application. The feature allows bulk uploading of work plans and activities through Excel files with built-in validation and error handling.

## Features

### 1. Work Plan Import

- **Location**: Master Data → Work Plans → Import Excel button
- **Template**: `public/templates/workplan_template.xlsx`
- **Supported Formats**: XLSX, XLS, CSV
- **Maximum File Size**: 5 MB

#### Work Plan Template Columns

| Column | Type   | Required | Description                                              |
| ------ | ------ | -------- | -------------------------------------------------------- |
| Code   | String | Yes      | Unique identifier for the work plan (max 255 characters) |
| Title  | String | Yes      | Name/title of the work plan (max 255 characters)         |

#### Features

- Automatic creation of new work plans
- Automatic update of existing work plans (matched by Code)
- Duplicate code validation
- Empty row skipping
- Detailed error reporting

### 2. Activity Import

- **Location**: Master Data → Activities → Import Excel button
- **Template**: `public/templates/activity_template.xlsx`
- **Supported Formats**: XLSX, XLS, CSV
- **Maximum File Size**: 5 MB

#### Activity Template Columns

| Column         | Type   | Required | Description                                                              |
| -------------- | ------ | -------- | ------------------------------------------------------------------------ |
| Work Plan Code | String | Yes      | Code of the parent work plan (must exist in system)                      |
| Code           | String | Yes      | Unique identifier for the activity within work plan (max 255 characters) |
| Title          | String | Yes      | Name/title of the activity (max 255 characters)                          |
| Description    | String | No       | Description of the activity                                              |

#### Features

- Automatic creation of new activities
- Automatic update of existing activities (matched by Work Plan Code + Code)
- Work plan code validation (must exist)
- Duplicate code validation per work plan
- Empty row skipping
- Detailed error reporting

## How to Use

### Step 1: Download Template

1. Navigate to Master Data → Work Plans or Master Data → Activities
2. Click the "Import Excel" button
3. Click "Download template" link in the modal
4. The template file will be downloaded to your computer

### Step 2: Fill Data

1. Open the downloaded template file in Excel, Google Sheets, or any spreadsheet application
2. Fill in the required columns according to the column specifications
3. Do not modify the header row
4. For Work Plans: Fill Code and Title
5. For Activities: Fill Work Plan Code, Code, and Title (Description is optional)

### Step 3: Upload File

1. Click the "Import Excel" button in the Web Interface
2. Select the filled template file from your computer
3. Click "Import" button
4. Wait for the import to complete

### Step 4: Review Results

After import completes, a summary message will show:

- Number of records successfully imported
- Number of failed records
- Specific error messages for failed rows (first 10 errors shown)

## File Structure

### Imports Directory

- `app/Imports/WorkPlanImport.php` - Handles WorkPlan data import logic
- `app/Imports/ActivityImport.php` - Handles Activity data import logic

### Console Commands

- `app/Console/Commands/GenerateImportTemplates.php` - Generates Excel template files

### Templates

- `public/templates/workplan_template.xlsx` - Download template for work plans
- `public/templates/activity_template.xlsx` - Download template for activities

### Routes

- `GET /templates/download/workplan` - Download WorkPlan template
- `GET /templates/download/activity` - Download Activity template

### Livewire Components

- `app/Livewire/MasterData/WorkPlans.php` - Work plans management with import
- `app/Livewire/MasterData/Activities.php` - Activities management with import

### Views

- `resources/views/livewire/master-data/work-plans.blade.php` - Work plans UI with upload modal
- `resources/views/livewire/master-data/activities.blade.php` - Activities UI with upload modal

## Error Handling

### Common Errors and Solutions

#### "File upload failed: File too large"

- **Solution**: Ensure file size is under 5 MB

#### "File upload failed: Invalid file format"

- **Solution**: Use only XLSX, XLS, or CSV files

#### "Row X: Code and Title are required"

- **Solution**: Ensure all required columns have values in that row

#### "Row X: Work Plan with code 'XXX' not found"

- **Solution**: Verify the work plan code exists in the system before importing activities

#### "Row X: Invalid file format"

- **Solution**: Ensure the Excel file has the correct structure with proper headers

### Validation Rules

**Work Plans:**

- Code: Required, string, max 255 characters, unique
- Title: Required, string, max 255 characters

**Activities:**

- Work Plan Code: Required, must match an existing work plan
- Code: Required, string, max 255 characters, unique per work plan
- Title: Required, string, max 255 characters
- Description: Optional, any text

## Technical Details

### Import Process

1. File is uploaded to Livewire component
2. File is validated (format, size)
3. maatwebsite/excel processes the file
4. Each row is validated against rules
5. Valid rows are imported (create or update)
6. Failed rows are collected with error messages
7. Summary is displayed to user

### Key Dependencies

- `maatwebsite/excel` - Excel processing
- `phpoffice/phpspreadsheet` - Spreadsheet manipulation
- `livewire/livewire` - Real-time component updates

## Permissions

Both import features require appropriate permissions:

- Work Plan Import: `masterdata.workplan.manage`
- Activity Import: `masterdata.activity.manage`

These permissions are checked in the middleware on the download routes.

## Tips and Best Practices

1. **Always start with a template**: Don't create Excel files from scratch; download and use the provided templates
2. **Use consistent formatting**: Follow the column order and names exactly as shown in the template
3. **Validate data before import**: Check for duplicates and required fields in your spreadsheet before importing
4. **Import work plans first**: If you're importing both work plans and activities, import work plans first
5. **Check error messages**: If import fails, read the detailed error messages to identify the issue
6. **Test with small datasets**: Start with a few rows to ensure the import works correctly
7. **Keep templates updated**: If the template structure changes, download the latest template

## Troubleshooting

### Q: Why did my import fail?

A: Check the error message displayed after import. Look for specific row numbers and validation errors.

### Q: Can I update existing records?

A: Yes! If a work plan/activity with the same code exists, it will be updated with the new data from the file.

### Q: What happens to related data when I update a record?

A: Only the fields in the template are updated. Other related data (like activity-COA mappings) are preserved.

### Q: Can I import without using the template?

A: It's not recommended. The template ensures proper column names and order. Manually created files may have formatting issues.

### Q: How many rows can I import at once?

A: The system supports importing multiple rows, but it's recommended to keep files under 1000 rows for optimal performance.

### Q: What happens if the import process is interrupted?

A: Imported data up to the point of interruption may be partially committed to the database. Check the summary message for exact numbers.

## Future Enhancements

Potential improvements for this feature:

1. Batch import with progress tracking
2. Import scheduling/automation
3. Template customization options
4. Advanced error reporting and export
5. Rollback functionality for failed imports
6. Import history tracking
7. Data preview before import
8. Duplicate detection and handling options

---

**Last Updated**: 2026-06-05
**Version**: 1.0
**Author**: System Administrator
