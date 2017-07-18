<div class="wrap">
    <h1>Storychief settings</h1>

    <p><?php esc_html_e('Storychief eliminates time waisting. To set up Storychief, enter you encryption key given to you by Story Chief.', 'storychief'); ?></p>

    <form action="<?php echo esc_url(Storychief_Admin::get_page_url()); ?>" method="post">
        <input type="hidden" name="action" value="enter-key"><?php wp_nonce_field(Storychief_Admin::NONCE); ?>
        <table class="form-table">
            <tbody>
            <tr>
                <th scope="row"><label
                            for="key"><?php esc_html_e('Enter your Story Chief Key', 'storychief'); ?></label></th>
                <td>
                    <input id="key" name="key" type="password" size="15" value="<?php echo $encryption_key; ?>"
                           class="regular-text">
                    <p class="description"><?php esc_html_e('Your encryption key is given when you add a Wordpress destination on Story Chief', 'storychief'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label
                            for="key"><?php esc_html_e('Your Wordpress url', 'storychief'); ?></label></th>
                <td>
                    <input type="text" size="15" value="<?php echo $wp_url; ?>" class="regular-text" readonly>
                    <p class="description"><?php esc_html_e('Save this in your Story Chief Configuration', 'storychief'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label
                            for="test_mode"><?php esc_html_e('Testing mode', 'storychief'); ?></label></th>
                <td>
                    <input type="checkbox" name="test_mode" value="1" <?php echo ($test_mode == 1)? 'checked' : '' ?>> Enable test mode<br>
                    <p class="description"><?php esc_html_e('Test mode will ensure your articles will not be set as published.', 'storychief'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label
                            for="author_create"><?php esc_html_e('Create unknown authors', 'storychief'); ?></label></th>
                <td>
                    <input type="checkbox" name="author_create" value="1" <?php echo ($author_create == 1)? 'checked' : '' ?>> Enable creation of unknown authors<br>
                    <p class="description"><?php esc_html_e('This option allows you to automatically create new authors in Wordpress when needed.', 'storychief'); ?></p>
                </td>
            </tr>
            </tbody>
        </table>


        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary"
                   value="<?php esc_attr_e('Save changes', 'storychief'); ?>">
        </p>
    </form>
</div>