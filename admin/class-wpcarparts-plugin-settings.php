<?php

/**
 * The settings of the plugin.
 *
 * @link       https://4real.no
 * @since      1.0.0
 *
 * @package    Wpcarparts
 * @subpackage Wpcarparts/admin
 */

/**
 * Class WordPress_Plugin_Template_Settings
 *
 */
class Wpcarparts_Admin_Settings
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {

        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * This function introduces the theme options into the 'Appearance' menu and into a top-level
     * 'Carparts' menu.
     */
    public function setup_plugin_options_menu()
    {

        //Add the menu to the Plugins set of menu items
        add_menu_page(
            'Carparts Uploader',                     // The title to be displayed in the browser window for this page.
            'OEM Carparts',                    // The text to be displayed for this menu item
            'manage_options',                    // Which type of users can see this menu item
            'carparts-uploader',            // The unique ID - that is, the slug - for this menu item
            array($this, 'render_settings_page_content'), // The name of the function to call when rendering this menu's page
            'dashicons-list-view', // The icon to be displayed on the menu. Dashicons: https://developer.wordpress.org/resource/dashicons/
            56 // The posistion of the menu
        );
    }


    /**
     * Renders a simple page to display for the theme menu defined above.
     */
    public function render_settings_page_content()
    {
?>
        <!-- Create a header in the default WordPress 'wrap' container -->
        <div class="wrap">
            <h1 class="wp-heading-inline"><?= __('Carparts CSV-uploader', 'Wpcarparts'); ?></h1>
            <form method='post' action='<?= $_SERVER['REQUEST_URI']; ?>' enctype='multipart/form-data'>
                <input class="button" type="file" name="import_file" style="padding: 10px;">
                <?php submit_button(); ?>
            </form>
        </div> <!-- /.wrap -->
<?php
    }

    /**
     * Set up of the sql-table 'carpart' where the data will be stored.
     */
    public function set_up_plugin_table()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $tablename = $wpdb->prefix . "carpart";

        // Check that the table doesen't exist
        if ($wpdb->get_var("show tables like '$tablename'") != $tablename) {

            $sql = "CREATE TABLE $tablename (
        id mediumint(11) NOT NULL AUTO_INCREMENT,
        item_number varchar(128) NOT NULL,
        name varchar(128) NOT NULL,
        price mediumint(11) NOT NULL,
        weight mediumint(11) NOT NULL,
        PRIMARY KEY (id)
        ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
        return register_activation_hook(__FILE__, 'set_up_plugin_table');
    }


    public function parse_and_save_csv()
    {
        global $wpdb;

        // Table name
        $tablename = $wpdb->prefix . "carpart";

        // Import CSV
        if (isset($_POST['submit'])) {

            // File extension
            $extension = pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION);

            // If file extension is 'csv'
            if (!empty($_FILES['import_file']['name']) && $extension == 'csv') {

                $totalInserted = 0;

                // Open file in read mode
                $csvFile = fopen($_FILES['import_file']['tmp_name'], 'r');

                // fgetcsv($csvFile); // Skipping header row

                // Read file
                while (($csvData = fgetcsv($csvFile)) !== FALSE) {
                    $csvData = array_map("utf8_encode", $csvData);

                    // Row column length
                    $dataLen = count($csvData);

                    // Skip row if length != 4
                    if (!($dataLen == 4)) continue;

                    // Assign value to variables
                    $itemNumber = trim($csvData[0]);
                    $itemName = trim($csvData[1]);
                    $itemWeight = trim($csvData[2]);
                    $itemPrice = trim($csvData[3]);


                    // Check if variable is empty or not
                    if (!empty($itemNumber) && !empty($itemName) && !empty($itemWeight) && !empty($itemPrice)) {

                        // Check record already exists or not
                        $cntSQL = "SELECT count(*) as count FROM {$tablename} where item_number='" . $itemNumber . "'";
                        $record = $wpdb->get_results($cntSQL, OBJECT);


                        $record[0]->count == 0 ?
                            // Insert Record
                            $wpdb->insert($tablename, array(
                                'item_number' => $itemNumber,
                                'name' => $itemName,
                                'price' => $itemWeight,
                                'weight' => $itemPrice
                            ))
                            : // Update record
                            $wpdb->update(
                                $tablename,
                                array(
                                    'item_number' => $itemNumber,
                                    'name' => $itemName,
                                    'price' => $itemWeight,
                                    'weight' => $itemPrice
                                ),
                                array(
                                    'item_number' => $itemNumber
                                )
                            );

                        if ($wpdb->insert_id > 0) {
                            $totalInserted++;
                        }
                    }
                }
                echo "<h3 style='color: green;'>Total record inserted: " . $totalInserted . "</h3>";
            } else {
                echo "<h3 style='color: red;'>Invalid Extension</h3>";
            }
        }
    }
}
