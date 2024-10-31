<tr valign="top">
    <th scope="row" class="titledesc"><?php esc_html_e( 'Logo:', $this->domain ); ?></th>
    <td>
        <label for="logo-upload" class=" button" style="">
            <i class="fa fa-cloud-upload"></i> <span id="logo-upload_label">Upload new payment logo</span>
        </label>
        <input id="logo-upload" type="file" name="logo"/>
    </td>
</tr>
<tr valign="top">
    <th scope="row" class="titledesc"><?php esc_html_e( 'Surcharge configuration:', $this->domain ); ?></th>
    <td class="forminp" id="vat_detail_list">
        <div class="wc_input_table_wrapper">
            <table class="widefat wc_input_table sortable" cellspacing="0">
                <thead>
                <tr>
                    <th class="sort">&nbsp;</th>
                    <th><?php esc_html_e( 'Country', $this->domain ); ?></th>
                    <th><?php esc_html_e( 'VAT in %', $this->domain ); ?></th>
                    <th><?php esc_html_e( 'Payout in %', $this->domain ); ?></th>
                    <th><?php esc_html_e( 'Surcharge Fee Description', $this->domain ); ?></th>
                    <th><?php esc_html_e( 'Version', $this->domain ); ?></th>
                </tr>
                </thead>
                <tbody class="accounts">
                <?php
                $i = -1;
                if ( $this->vat_detail ) {
                    foreach ( $this->vat_detail as $detail ) {
                        $i++;
                        echo '<tr class="account">
                                    <td class="sort"></td>
                                    <td><input type="text" value="' . esc_attr( wp_unslash( $detail['country'] ) ) . '" name="payment_country[' . esc_attr( $i ) . ']" /></td>
                                    <td><input type="text" value="' . esc_attr( $detail['vat'] ) . '" name="payment_vat[' . esc_attr( $i ) . ']" /></td>
                                    <td><input type="text" value="' . esc_attr( wp_unslash( $detail['payout'] ) ) . '" name="payment_payout[' . esc_attr( $i ) . ']" /></td>
                                    <td><input type="text" value="' . esc_attr( $detail['description'] ) . '" name="payment_description[' . esc_attr( $i ) . ']" /></td>
                                    <td><input type="text" value="' . esc_attr( $detail['version'] ) . '" name="payment_version[' . esc_attr( $i ) . ']" /></td>
                                </tr>';
                    }
                }
                ?>
                </tbody>
                <tfoot>
                <tr>
                    <th colspan="6">
                        <a href="#" class="add button"><?php esc_html_e( '+ Add new detail', $this->domain ); ?></a>
                        <a href="#" class="remove_rows button"><?php esc_html_e( 'Remove selected detail', $this->domain ); ?></a>
                    </th>
                </tr>
                </tfoot>
            </table>
            <div style="padding: 10px;">
                <label for="file-upload" class=" button" style="">
                    <i class="fa fa-cloud-upload"></i> <span id="import_label">Import CSV</span>
                </label>
                <input id="file-upload" type="file" name="csv_data"/>
                <a href="<?php echo ONEBIP_PLUGIN_URL . "/sample.csv" ?>" style=" line-height: 28px; margin-left: 10px;" class="">Sample CSV</a>
                <style>
                    input[type="file"] {
                        display: none;
                    }
                </style>
            </div>
        </div>
        <script type="text/javascript">
            jQuery(function() {
                jQuery('#file-upload').change(function() {
                    jQuery("#import_label").html(jQuery(this).val().split('\\').pop())
                });
                jQuery('#logo-upload').change(function() {
                    jQuery("#logo-upload_label").html(jQuery(this).val().split('\\').pop())
                });
                jQuery('#vat_detail_list').on( 'click', 'a.add', function(){
                    var size = jQuery('#vat_detail_list').find('tbody .account').length;
                    console.log(size);
                    jQuery('<tr class="account">\
    									<td class="sort"></td>\
    									<td><input type="text" name="payment_country[' + size + ']" /></td>\
    									<td><input type="text" name="payment_vat[' + size + ']" /></td>\
    									<td><input type="text" name="payment_payout[' + size + ']" /></td>\
    									<td><input type="text" name="payment_description[' + size + ']" /></td>\
    									<td><input type="text" name="payment_version[' + size + ']" /></td>\
    								</tr>').appendTo('#vat_detail_list table tbody');
                    return false;
                });
            });
        </script>
    </td>
</tr>
