## v2.1.6

### ✨ Improvements

- Shortened Income/Expense bar labels on the Graphs page to **Inc.** and **Exp.** for cleaner display

---

## v2.1.5

- Added direct **Income** and **Expense** labels underneath each bar on the Graphs page stacked bar view
- Increased stacked bar chart bottom spacing so the per-bar labels stay readable
- Updated Graphs wiki page

----------------------------------------

## v2.1.3

- Fixed Graphs stacked-by-app legend so selected apps stay visible in the legend color key
- Kept stacked graph app colors consistent with the selected app filter order
- Updated Graphs wiki page

----------------------------------------
v2.1.2
----------------------------------------

- Fixed stacked Income vs Expense graph app segments rendering in their assigned colors instead of black
- Corrected stacked bar visual styling so app breakdowns display properly while keeping the legend in sync

# 📘 RewardLedger – Version History

All notable changes to this application are documented here.

---


## 🚀 v2.1.1

### 🛠 Improvements

* Added stacked-by-app mode to the Income vs Expense graph.
* Added an Income / Expense View selector so users can switch between stacked app bars and combined totals.
* Added an app legend to the stacked bars so users can see which app makes up each section of each bar.

---


## 🚀 v2.1.0

### ✨ Features

* Added a new Graphs page with a local self-contained chart system.
* Added Net Profit Over Time, Income vs Expense, and Category Breakdown charts.
* Added graph filters for date mode, custom date range, grouping, apps, categories, and assets.
* Added summary totals above the charts for income, expense, and net profit.

---

## 🚀 v2.0.6

### 🔧 Fixes

* Fixed Templates drag-and-drop reorder saving in Settings so the new order now persists after refresh.
* Standardized the Templates reorder endpoint to match the working reorder handlers used elsewhere in Settings.

---

## 🚀 v2.0.5

### 🔧 Fixes

* New templates now save using the next available sort order instead of defaulting to **0**
* New Quick Entry items now save using the next available sort order instead of defaulting to **0**
* Quick Entry items created from template lines now also use the next available sort order
* Quick Entry sort numbers now update on screen immediately after drag-and-drop in Settings

---

## 🚀 v2.0.4

### 🛠 Fixes
* Fixed Assets drag-and-drop reordering so the visible Sort column updates immediately after rows are moved.
* Standardized Assets sort numbering to save in 1-based order so it matches Accounts.

---

## 🚀 v2.0.3

### 🛠 Improvements
* Added Database Maintenance tools to Settings → Other.
* Added Optimize Database action for safe table cleanup and overhead reduction.
* Added Repair Database action for safe table repair checks.
* Added maintenance stats and guidance in the Other tab.
* Updated wiki to document the new maintenance tools.

---

## 🚀 v2.0.1

### 🛠 Improvements
* 0.000000 in "Amount" in Quick Entry page now disappears when clicked.
* 0.000000 in "Amount" in Templates Use page now disappears when clicked.
* Finished Wiki.

## 🚀 v2.0.0

### ✨ Major Features

* Introduced **multi-currency support**
* Added **multi-app support enhancements**
* Implemented **Quick Entry system improvements**
* Expanded **template system functionality**
* Added **reporting system** with date range selection
* Introduced **interactive dashboard charts**
* Added **centralized Settings page** with tab-based navigation

---

### 🧩 New Features & Enhancements

* Added default currencies and currency symbol handling
* Enabled **drag-and-drop dashboard tile reordering**
* Persisted dashboard layout between sessions
* Added **drag-and-drop reordering in Settings sections**
* Added **bulk miner entry** on Miners page
* Added **multi-line entry support** for amounts in Quick Entry
* Added **multiple entry support** in Quick Entry items
* Added **tooltips** across the interface
* Moved “Create New Template” to Settings for better organization
* Enabled **Quick Add template edit/delete** without leaving Quick Entry
* Added **template deletion support**

---

### 🛠 Improvements

* Refined dashboard layout and usability
* Improved number formatting across dashboard
* Improved template, batch, and Quick Entry data consistency
* Standardized fiat handling (Cash consolidated into USD)
* Streamlined and simplified Change Log structure

---

### 🐛 Bug Fixes

* Fixed template and schema mismatch issues
* Fixed template-to-batch creation errors
* Fixed Quick Entry creation from template items
* Fixed template usage display issues
* Fixed account selection inconsistencies
* Fixed routing and page access issues
* Fixed template deletion being reversed by schema reseeding

---

### 🗄 Database & Backend

* Various schema improvements and optimizations
* Improved database integrity and consistency across features

---

## 🧪 v0.1.0-alpha

### ✨ Features

* Initial **multi-app support**
* Introduced **Quick Entry system**
* Added **template system**
* Implemented **basic dashboard with app tracking**

---

### 🔄 Changes

* Renamed application from **"GoMining Rewards Tracker" → "RewardLedger"**
* Updated branding for broader multi-app support

---

## 🧪 v0.1.0-alpha (Initial Test Release)

### ✨ Features

* First internal test release
----------------------------------------
v2.0.2
----------------------------------------

- Add Neutral behavior type for categories
- Set Daily Gross Rewards default behavior to Neutral
- Ignore Neutral and Transfer categories in ledger total calculations
- Style Investment dashboard cards in blue and Neutral cards in gray

