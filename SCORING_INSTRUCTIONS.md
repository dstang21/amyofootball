# Scoring Settings Instructions

## What You Created:

### 1. Database Table: `scoring_settings`
- **Purpose**: Store different fantasy football scoring configurations
- **Columns**:
  - `id` - Unique identifier
  - `name` - Name of the scoring system (e.g., "Standard PPR")
  - `passing_yards` - Points per passing yard (decimal, 2 places)
  - `passing_td` - Points per passing touchdown
  - `passing_int` - Points per interception (usually negative)
  - `rushing_yards` - Points per rushing yard
  - `rushing_td` - Points per rushing touchdown
  - `receptions` - Points per reception (PPR settings)
  - `receiving_yards` - Points per receiving yard
  - `receiving_tds` - Points per receiving touchdown
  - `fumbles_lost` - Points per fumble lost (usually negative)

### 2. Admin Page: `admin/manage-scoring.php`
- **Add/Edit/Delete** scoring settings
- **Pre-filled defaults** for common scoring systems
- **Clean interface** with organized sections (Passing, Rushing, Receiving, Penalties)

### 3. Dashboard Integration
- **New stat card** showing total scoring settings
- **Admin navigation** link added to sidebar

## How to Use:

### Step 1: Add the Database Table
Run the `scoring_settings.sql` file in your database to create the table and add sample data.

### Step 2: Access the Admin Panel
- Login to admin
- Go to "Scoring Settings" in the sidebar
- You'll see 4 pre-configured scoring systems

### Step 3: Customize Scoring
- **Edit existing** settings to match your league rules
- **Add new** scoring systems for different leagues
- **Delete** systems you don't need

## Common Scoring Examples:

### Standard PPR (Points Per Reception)
- Passing: 0.04 per yard, 4 pts per TD, -2 per INT
- Rushing: 0.10 per yard, 6 pts per TD
- Receiving: 1.00 per reception, 0.10 per yard, 6 pts per TD
- Penalties: -2 per fumble lost

### Half PPR
- Same as Standard PPR but 0.50 per reception

### Standard (No PPR)
- Same as PPR but 0.00 per reception

## Future Integration:
These scoring settings can later be used to:
- Calculate projected fantasy points for players
- Create different ranking systems
- Build fantasy point calculators
- Compare players across different scoring systems

The system is designed to be flexible and easily expandable for more advanced fantasy football features.
