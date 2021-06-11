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
            <p>Upload a comma-separated values(CSV)-file to add original parts.</p>
            <p>The format is: article number, name, price, weight.</p>
            <form method='post' action='<?= $_SERVER['REQUEST_URI']; ?>' enctype='multipart/form-data'>
                <div class="form-field">
                <input class="button" type="file" name="import_file" style="padding: 10px;margin-top: 20px;">
                </div>
                <?php submit_button("Load the CSV-file"); ?>
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
        item_number varchar(128) NOT NULL,
        item_name varchar(128) NOT NULL,
        item_price mediumint(11) NOT NULL,
        item_weight mediumint(11) NOT NULL,
        PRIMARY KEY (item_number)
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

                // For sucess messages
                $totalInserted = 0;
                $totalUpdated = 0;

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
                        if ($wpdb->update_id > 0) {
                            $totalUpdated++;
                        }
                    }
                }
                echo "<div class='notice notice-success is-dismissible'><p>" . $totalInserted . " new records.</p></div>";
                echo "<div class='notice notice-success is-dismissible'><p>" . $totalUpdated . " updated records.</p></div>";
            } else {
                echo "<div class='notice notice-error is-dismissible'><p>" . $_FILES['import_file']['name'] . " is not a valid CSV-file.</p></div>";
            }
        }
    }

    /**
     *
     * The idea of the code below is how to break up a large CSV file into parts
     * and then write the individual files to disk.
     */
    public function parse_split_csv()
    {
        // Array to push rows with more than 4 columns
        $faulty_rows = array();

        // Import CSV
        if (isset($_POST['submit'])) {

            // File extension
            $extension = pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION);

            // If file extension is 'csv'
            if (!empty($_FILES['import_file']['name']) && $extension == 'csv') {

                // Read the contents of the file
                $row = 0;
                $input_data = array();
                $inputedRows = 0;
                if (($handle = fopen($_FILES['import_file']['tmp_name'], "r")) !== FALSE) {
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $num = count($data);
                        $row++;

                        // Save rows that don't contain four values and skip that row
                        if (!($num == 4)) {
                            array_push($faulty_rows, $row);
                            continue;
                        }

                        // Trim all data from white space start and end
                        for ($c = 0; $c < $num; $c++) {
                            $inputedRows++;
                            $data[$c] = trim($data[$c]);
                        }
                        // Push row data to array
                        array_push($input_data, $data);
                        if ($row === 250) {
                            $this->save_to_database($input_data);
                            $input_data = array();
                            $row = 0;
                        }
                    }
                    if (count($input_data)) {
                        $this->save_to_database($input_data);
                    }
                    fclose($handle);
                }
            }
            // Not a csv
            else {
                echo "<div class='notice notice-error is-dismissible'><p>" . $_FILES['import_file']['name'] . " is not a valid CSV-file.</p></div>";
            }
        }
        if (empty(!$faulty_rows)) {
            $numItems = count($faulty_rows);
            $i = 0;

            echo "<div class='notice notice-warning is-dismissible'><p>Row id(s): ";
            foreach ($faulty_rows as $value) {
                // last index of array
                if (++$i === $numItems) {
                    echo $value;
                } else {
                    echo "$value, ";
                }
            }
            echo " don't contains 4 values.</p></div>";
        }
        if ($inputedRows > 0) {
            echo "<div class='notice notice-success is-dismissible'><p>" . $inputedRows  . " records added or updated.</p></div>";
        }
    }
    public function save_to_database($input_data)
    {
        global $wpdb;
        $tablename = $wpdb->prefix . "carpart";
        $place_holders = array();
        $values = array();
        foreach ($input_data as $data) {
            $place_holders[] = "( '%s', '%s', '%d', '%d')";
            array_push($values, $data[0], $data[1], $data[2], $data[3]);
        }
        $query  = "INSERT INTO `$tablename` (`item_number`,`item_name`,`item_price`,`item_weight`) VALUES ";
        $query .= implode(',', $place_holders);
        $query .= 'ON DUPLICATE KEY UPDATE item_name=VALUES(item_name), item_price=VALUES(item_price),item_weight=VALUES(item_weight)';
        $sql    = $wpdb->prepare($query, $values);
        if ($wpdb->query($sql)) {
            return true;
        } else {
            return false;
        }
    }
}
