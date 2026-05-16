### [1]
Act as an expert software engineer. Help me implement a bulk **Inventory Adjustment Import** feature using a CSV file. This feature will be used by warehouse managers during the **end-of-year stock take** process.

### Functional Requirements:
1. **Bulk Upload:** The system must process a CSV file to update inventory quantities in bulk.
2. **Handle Discrepancies:** The stock take will count all physical items. If an item is found physically but is **not currently recorded** in the system, the import must handle this case (e.g., flag it for creation or auto-create it with a default status).
3. **CSV Template Validation:** Provide a standard CSV template structure. The import logic must strictly validate that incoming files match this exact template format before processing.

### Technical Deliverables Needed:
* **Database Schema:** Any necessary table changes for tracking adjustments.
* **Backend Logic:** Code to parse the CSV, validate data types, and execute the inventory adjustments safely (preferably within a database transaction).
* **CSV Template Example:** A sample text representation of the required CSV structure with headers and dummy data.
* **Error Handling:** How to handle missing headers, invalid quantities, or unknown SKU formats.


### [2]
Act as a senior software engineer. Help me implement a "Download Inventory Template" feature that generates a pre-formatted CSV file for users.

### Functional Requirements:
1. **Dynamic Generation:** The system must query the active database and list ALL current inventory items in the CSV template.
2. **Sorting and Grouping:** The generated rows must be strictly grouped and sorted by:
   * Warehouse Name / ID
   * Item Category
   * Brand
3. **Template Purpose:** This CSV will be used by staff for data entry or stock updates, so it needs empty columns for data input alongside the system's item data.

### Technical Deliverables Needed:
* **Backend Logic:** Code to fetch, group, and stream the data into a CSV format efficiently (handling large datasets without memory crashes).
* **CSV Schema/Headers:** A clear layout of the columns, including system reference data (SKU, Name, Warehouse, Category, Brand) and the target entry fields.
