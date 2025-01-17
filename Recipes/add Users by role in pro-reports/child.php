<?php 

add_filter( 'custom_mainwp_sync_data', 'send_prebuilt_user_table_html_to_mainwp' );

function send_prebuilt_user_table_html_to_mainwp( $custom_data ) {
    // Fetch all users and their roles
    $all_users = get_users();
    $users_by_role = [];

    foreach ( $all_users as $user ) {
        foreach ( $user->roles as $role ) {
            if ( ! isset( $users_by_role[ $role ] ) ) {
                $users_by_role[ $role ] = [];
            }
            $users_by_role[ $role ][] = [
                'username' => esc_html( $user->user_login ),
                'email'    => esc_html( $user->user_email ),
            ];
        }
    }

    // Generate the HTML table
    $output = '<table border="1" cellpadding="10" cellspacing="0" width="100%">
        <thead>
            <tr>
                <th>Role</th>
                <th>Username</th>
                <th>Email</th>
            </tr>
        </thead>
        <tbody>';

    foreach ( $users_by_role as $role => $users ) {
        foreach ( $users as $user ) {
            $output .= '<tr>
                <td>' . ucfirst( $role ) . '</td>
                <td>' . $user['username'] . '</td>
                <td>' . $user['email'] . '</td>
            </tr>';
        }
    }

    $output .= '</tbody></table>';

    // Add the prebuilt HTML to the custom data
    $custom_data['rup_all_users_table_html'] = $output;

    return $custom_data;
}


