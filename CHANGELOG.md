# 📘 RewardLedger – Version History

All notable changes to this application are documented here.

## 2.2.1
- Fixed ledger edit form not loading "Time Received"
- Fixed ledger edit form not loading "Value at Time Received"

---

## 2.2.0
- Added BTC Sats mode across Quick Entry, Templates, and Ledger edit
- Added per-template and per-quick-entry sats default setting
- Integrated sats support into value lookup at time received
- Added Graphs page with Net Profit, Income vs Expense, Category Breakdown
- Added stacked bar support by app
- Improved graph readability with Inc./Exp. labels under bars
- Fixed graph legend inconsistencies
- Fixed Quick Entry sats default behavior and template integration
- Fixed Quick Entry script scoping issue affecting sats checkbox
- General UI/UX improvements and consistency updates

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

