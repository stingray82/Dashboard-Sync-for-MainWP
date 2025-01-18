**Dashboard Sync for MainWP**
=============================

### **Overview**

The Dashboard Sync plugin is a MainWP extension that allows the MainWP dashboard
to:

-   Receive custom data from child sites.

-   Process and store this data for reporting and management purposes.

-   Integrate child site data into MainWP Pro Reports using custom tokens.

 

![](https://github.com/stingray82/repo-images/raw/main/Dashboard-Sync-for-MainWP/Dashboard-sync.png)

 

### **Current Features**

1.  **Receive Data from Child Sites**:

    -   Automatically pulls custom data sent from child sites during a MainWP
        sync.

2.  **Custom Prefixing**:

    -   Uses a configurable prefix (`custom_mainwp_prefix`) to namespace synced
        data, preventing conflicts with other plugins.

3.  **MainWP Pro Reports Tokens**:

    -   Dynamically generates tokens for Pro Reports based on synced data.

4.  **Child Data Cleanup**:

    -   Includes an admin tool to delete synced data for all child sites by
        prefix.

5.  **Dynamic Admin Pages**:

    -   Supports the addition of custom admin pages to manage child
        site-specific settings.

6.  **Wildcard Data Fetching**:

    -   Enables fetching child site options dynamically using a wildcard, useful
        for querying multiple related options.

### **Installation**

1.  Upload the `dashboard-sync.zip` file to your MainWP Dashboard site.

2.  Activate the plugin via the Plugins menu.

3.  Navigate to the **MainWP Extensions** section to configure settings.

 

![](https://github.com/stingray82/repo-images/raw/main/Dashboard-Sync-for-MainWP/Dashboard-sync.png)

 

1.  Set your custom Prefix in the extensions menu this must be the same on
    **ALL** Child sites

 

![](https://github.com/stingray82/repo-images/raw/main/Dashboard-Sync-for-MainWP/Dashboard-Sync-MainWP.png)

1.  Optionally if you have recipes and you want to activate them, you enable
    them in this same menu.

### **How It Works**

1.  **Receiving Data**:

    -   Hooks into `mainwp_site_synced` to process and store custom data sent
        from child sites.

    -   Data is saved in the MainWP options table using the configured prefix.

2.  **Pro Reports Integration**:

    -   Adds tokens for synced data, making it accessible in MainWP Pro Reports.

    -   Tokens are dynamically generated based on the synced keys.

3.  **Child Data Cleanup**:

    -   Adds a cleanup tool to the **Tools** menu, allowing deletion of synced
        data based on the prefix.

 

### **Usage**

#### **Sync Data from Child Sites**

Child sites send custom data during a MainWP sync. The data is stored in the
MainWP options table for later use.

#### **Pro Reports Tokens**

Tokens for synced data can be included in reports using the format
`[prefix_key]`.

#### **Generated Admin Menu**

These need enabling within the Extensions Menu

 

#### **Clean Synced Data**

1.  Go to **Tools \> MainWP Cleanup**.

2.  Click **Clean Up Data** to delete all synced data for the configured prefix.

 

 

**MainWP Child Sync Plugin**
============================

### **Overview**

The MainWP Child Sync plugin enables child sites to:

-   Send custom data to the MainWP Dashboard during a sync.

-   Use a configurable prefix to organize and namespace the data being synced.

### **Current Features**

1.  **Custom Data Sync**:

    -   Sends child site-specific data to the dashboard during a MainWP sync.

2.  **Configurable Prefix**:

    -   Uses a customizable prefix (`custom_mainwp_prefix`) for namespacing
        synced data.

3.  **Admin Settings Page**:

    -   Includes an admin settings page for configuring the prefix.

4.  **Dynamic Data Addition**:

    -   Provides a filter (`custom_mainwp_sync_data`) for developers to add
        custom data to the sync.

### **Installation**

1.  Upload the `child-sync.zip` file to each child site.

2.  Activate the plugin via the Plugins menu.

3.  Navigate to **Settings \> MainWP Sync** to configure the prefix.

 

![](https://github.com/stingray82/repo-images/raw/main/Dashboard-Sync-for-MainWP/Child-Prefix.png)

 

### **How It Works**

1.  **Custom Data Sync**:

    -   During a MainWP sync, data is gathered and sent to the dashboard using
        the `mainwp_site_sync_others_data` filter.

2.  **Prefix Management**:

    -   Ensures all synced data is namespaced to avoid conflicts with other
        plugins.

### **Usage**

#### **Add Custom Data to Sync**

Developers can add data using the `custom_mainwp_sync_data` filter:

~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
phpCopyEditadd_filter('custom_mainwp_sync_data', function($custom_data) {
    $custom_data['example_key'] = get_option('example_option', 'default_value');
    return $custom_data;
});
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

#### **Configure Prefix**

1.  Go to **Settings \> MainWP Sync**.

2.  Set the desired prefix in the provided field.

**Important Notes**
===================

-   **Current Limitation**: Sync is one-directional, with data flowing from
    child sites to the dashboard. Dashboard-to-child actions are not yet
    implemented.

-   **Custom Pro Reports**: Tokens are generated only for data received from
    child sites.

 

 

**Recipes ​​**
============

The plan for this project is it allows the building of a framework for easier
recipes to make plugin integrations lighter weight and easier so we can
integrate, use our favorite plugins with Main WP, it is now easier and lighter
to include any child site data in pro-reports, add custom menus and pages to
display that information within a child site and eventually the idea is for a
clear bi-directional and even live updates and actions to allow, cache clearing
buttons, run flows in WordPress automation plugins and update plugin settings
all from your dashboard.

 

The idea behind recipes are they are quick to deploy, offer the option of just
pro-report tokens or an interface too, if enabled within the extensions menu to
make them easier and quicker to deploy, if you don’t need the client site menu
just don’t enable it, if you don’t want the tokens don’t use them

They are all designed to be installed in a snippet plugin (I recommend
[WPCODEBOX ​).](https://wpcodebox.com)

*In most cases there will be a snippet (recipe) for the child site and the
dashboard site they could also be deployed using the MainWP snippet manager and
this is why they have been designed this way.*

 

**Current Recipes include:**
----------------------------

 

### **1. FlowMattic Information**

Introduces the following new report tokens

-   **Flowmattic Workflows:**
    `[***Customprefix***_rup_mainwp_flowmattic_workflows_count]`

-   **Flowmattic Tables:**
    `[***Customprefix***_rup_mainwp_flowmattic_tables_count]`

-   **Flowmattic AI Assistants:**
    `[***Customprefix***_rup_mainwp_flowmattic_ai_assistants_count]`

-   **Flowmattic Connects:**
    `[***Customprefix***_rup_mainwp_flowmattic_connects_count]`

-   **Flowmattic Variables:**
    `[***Customprefix***_rup_mainwp_flowmattic_variables_count]`

-   **Flowmattic Integrations:**
    `[***Customprefix***_rup_mainwp_flowmattic_integrations_count]`

-   **Flowmattic Custom Apps:**
    `[***Customprefix***_rup_mainwp_flowmattic_custom_apps_count]`

-   **Flowmattic Tasks Executions:**
    `[***Customprefix***_rup_mainwp_flowmattic_tasks_executions_count]`

 

As well as if enabled within the extension menu, an optional child site menu
exists which looks like the below:

 

![](https://github.com/stingray82/repo-images/raw/main/Dashboard-Sync-for-MainWP/Flowmattic-receipe-new.png)

 

**Installation:**

Install the Child Snippet on the child site(s) and the dashboard snippet on the
dashboard site, sync data and the snippets and menus should be active and
populated.

 

### **2. WP-Armour Information**

Introduces the following new report tokens

-   **Blocked Today:** `[***Customprefix***_rup_wparmour_today_total]`

-   **Blocked This Week:** `[***Customprefix***_rup_wparmour_thisweek_total]`

-   **Blocked This Month:** `[***Customprefix***_rup_wparmour_thismonth_total]`

-   **Blocked All Time:** `[***Customprefix***_rup_wparmour_alltime_total]`

 

As well as if enabled within the extension menu, an optional child site menu
exists which looks like the below:

![](https://github.com/stingray82/repo-images/raw/main/Dashboard-Sync-for-MainWP/WP-Armour.png)

 

**Installation:**

Install the Child Snippet on the child site(s) and the dashboard snippet on the
dashboard site, sync data and the snippets and menus should be active and
populated.

 

### **3. Add Users by Role to Pro-reports**

Adds the following new report token, this only needs adding to the child sites
there is no admin menu so there is NO need for a dashboard snippet.

**Users By Role:** `[***Customprefix***`\_rup_all_users_table_html`]`

this is Pre-html formatted and looks like this in final report.

 

![](https://github.com/stingray82/repo-images/raw/main/Dashboard-Sync-for-MainWP/All Users.png)

 

 

**Future Ambitions**
====================

### **1. Bi-Directional Sync**

-   Extend the functionality to allow data and settings to flow **both ways**:

    -   Push settings or actions from the dashboard to child sites.

    -   Sync confirmations or live updates from child sites back to the
        dashboard.

### **2. Live Updates and Actions**

-   Enable the dashboard to trigger real-time actions on child sites:

    -   Example actions: Update plugin settings, clear caches, or run
        maintenance scripts.

-   Provide confirmation of action success or failure via synced responses.

### **3. Universal Reusability**

-   Build reusable functions and filters that make it easy to:

    -   Add new integrations with minimal code changes.

    -   Expand the system to include more complex use cases, such as managing
        third-party plugin settings.

### **4. Priority Mechanism for Conflict Resolution**

-   Implement a priority system using **transients** or timestamps:

    -   Recent changes made on the dashboard take precedence over child site
        changes.

    -   Seamlessly handle conflicts in a predictable manner.

**Key Ethos**
=============

-   **Reusable**: The plugins are designed to allow other developers to easily
    extend functionality without modifying core code.

-   **Expandable**: New integrations can be added using standardized filters and
    actions.

-   **Efficient**: Designed to reduce manual configuration and support scalable
    data handling.

-   **Heavy Lifting:** Is done by the plugins the snippets do the customisation.
