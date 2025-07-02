# Test Management Framework

## Overview

This is a web-based test management framework built with a traditional LAMP-style architecture (though currently showing frontend assets only). The application appears to be designed for managing software testing workflows including requirements, test cases, bug tracking, and test execution reporting.

## System Architecture

### Frontend Architecture
- **Technology Stack**: HTML, CSS, JavaScript (Vanilla JS)
- **UI Framework**: Bootstrap (based on CSS classes and styling patterns)
- **Architecture Pattern**: Module-based JavaScript with namespace organization
- **Styling**: CSS custom properties for theming with Bootstrap integration

### Backend Architecture
- **Expected Technology**: PHP (based on file structure and JavaScript comments referencing PHP endpoints)
- **Architecture Pattern**: Traditional server-side rendering with AJAX enhancements
- **API Design**: RESTful endpoints for data operations (referenced in JavaScript but not yet implemented)

### Data Storage
- **Database**: Not yet implemented (placeholder for future database integration)
- **Expected Schema**: Tables for requirements, test cases, bugs, users, and projects

## Key Components

### 1. User Interface Layer
- **Responsive Design**: Bootstrap-based responsive layout
- **Navigation**: Global navigation bar with hover effects
- **Cards**: Elevated card components for content organization
- **Forms**: Client-side validation with AJAX submission capability

### 2. JavaScript Application Layer
- **App Module** (`assets/js/app.js`): Core application initialization and global functionality
  - User session management
  - Global search functionality
  - Form validation and AJAX handling
  - Notification system
- **Dashboard Module** (`assets/js/dashboard.js`): Dashboard-specific functionality
  - Statistics loading and display
  - Chart initialization (placeholder for future chart library)
  - Real-time activity updates
  - Auto-refresh capabilities

### 3. Styling System
- **CSS Custom Properties**: Centralized color scheme using CSS variables
- **Component Styling**: Card components, navigation, and form elements
- **Interaction States**: Hover effects and transitions for better UX

## Data Flow

### Current Implementation
1. **Page Load**: JavaScript modules initialize and bind event listeners
2. **User Interactions**: Form submissions and searches trigger AJAX calls
3. **Data Display**: DOM manipulation updates UI with fetched data
4. **State Management**: Global App object maintains application state

### Expected Full Implementation
1. **Authentication**: User login/logout through PHP sessions
2. **CRUD Operations**: Create, read, update, delete operations for test artifacts
3. **Real-time Updates**: Dashboard statistics refresh automatically
4. **Search**: Global search across requirements, test cases, and bugs

## External Dependencies

### Current Dependencies
- **Bootstrap**: CSS framework for responsive design and components
- **Font Family**: Segoe UI fallback stack for cross-platform typography

### Expected Dependencies
- **Chart Library**: For dashboard visualizations (Chart.js or similar)
- **AJAX Library**: For API communications (or native fetch API)
- **Date/Time Library**: For timestamp formatting and manipulation

## Deployment Strategy

### Current State
- **Static Assets**: CSS and JavaScript files served directly
- **Development**: Local file-based development environment

### Production Considerations
- **Web Server**: Apache or Nginx for serving PHP and static assets
- **Database Server**: MySQL or PostgreSQL for data persistence
- **Caching**: Browser caching for static assets, server-side caching for dynamic content
- **Security**: Input validation, SQL injection prevention, XSS protection

## User Preferences

Preferred communication style: Simple, everyday language.

## Recent Changes

### July 01, 2025 - Test Execution Enhancement with Step-by-Step Tracking
**Major Update**: Enhanced test runs with detailed step execution tracking and evidence management
- **New Database Tables**: 
  - `test_step_executions` - Tracks execution status for each individual test step
  - `test_evidence` - Stores uploaded evidence files (screenshots, documents)
- **Enhanced Test Execution**:
  - **Table-based execution interface** for faster testing with minimal clicks
  - All test steps displayed in single responsive table with inline editing
  - Pass/fail status tracking per step with actual results recording
  - Evidence attachment capability for screenshots and documents
  - Real-time execution summary with pass/fail statistics
  - Overall test case result calculation based on step outcomes
- **New Features**:
  - Execute Steps page (`execute_steps.php`) with streamlined table interface
  - Bulk operations: "Mark All Passed/Failed/Blocked" buttons for quick execution
  - File upload system for evidence attachments (uploads/evidence/ directory)
  - Individual step status tracking (passed, failed, blocked, skipped, not_run)
  - Actual results field per step vs expected results comparison
  - Notes and evidence description capabilities
  - Enhanced test run interface with "Execute Steps" buttons
  - Auto-save functionality to prevent data loss during long test sessions
  - Keyboard shortcuts (Ctrl+S to save, Ctrl+1 for pass all, Ctrl+2 for fail all)
- **Professional Interface**: Color-coded table rows based on execution status, responsive design for efficient testing workflow

### July 01, 2025 - Test Case Module Enhancement  
**Major Update**: Restructured test case management with structured test steps
- **New Database Table**: Added `test_case_steps` table with columns: step_number, step_description, expected_result
- **Enhanced UI**: Replaced single textarea fields with dynamic table interface for test steps
- **Features Added**:
  - Interactive table with add/remove step functionality
  - Sequential step numbering with automatic reordering
  - Individual expected results per test step
  - Modal view for detailed test case display with formatted step table
  - AJAX-powered test case details viewer (`view_details.php`)
- **Backward Compatibility**: Legacy test cases still supported through view layer
- **Form Improvements**: Both create and edit forms now use structured step management
- **JavaScript Enhancements**: Dynamic step management with validation

This addresses the user requirement for comprehensive test execution tracking with evidence support.

## Changelog

Changelog:
- July 01, 2025. Initial setup
- July 01, 2025. Enhanced test case module with structured test steps table
- July 01, 2025. Added comprehensive test execution tracking with step-by-step execution, evidence attachments, and detailed results recording