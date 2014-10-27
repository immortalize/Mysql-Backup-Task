<?php
    set_error_handler( 'error_handler' );
    
    function error_handler($errno, $errmsg, $filename, $linenum, $vars) {
        if ( 0 === error_reporting() ) {
            return false;
        }
        if ( $errno !== E_ERROR ) {
            throw new \ErrorException( sprintf('%s: %s', $errno, $errmsg ), 0, $errno, $filename, $linenum );
        }
    }
    
    /**************************************************************************/
    /************************** Ayarlar ***************************************/
    /**************************************************************************/
    
    // Veritabanı ayarları
    $mysql_configs = array(
        'host' => 'localhost', //'192.168.1.104',
        'port' => '3306',
        'username' => 'username',
        'password' => 'password',
        'mysqldump_path' => ''
    );
    
    $export_path = 'C:\your-path\mysqldumper\\';
    $export_name = 'all_databases_'.date('d_m_Y_H_i_s', time()).'.sql.gz';
    
    $database_names = array(
        'database1',
        'database2'
    );
    
    // FTP Ayarları
    $ftp_upload = FALSE;
    
    $ftp_configs = array(
        'host' => '127.0.0.1',
        'username' => 'username',
        'password' => 'password'
    );
    
    //$ftp_remote_file = "C:/WebPages/upload/".$export_name; // sunucudaki dosya yolu
    $ftp_remote_file = "yedek/".$export_name; // sunucudaki dosya yolu
    $ftp_file = $export_path.$export_name; // yerel dosya yolu
    
    # Mail Ayarları
    $send_mail = TRUE;
    
    $mail_config['protocol'] = "smtp";
    $mail_config['port'] = "587";
    $mail_config['host'] = "smtp.example.com";
    $mail_config['username'] = "username"; 
    $mail_config['password'] = "password";
    
    $mail_config['sender'] = "sender@example.com";  
    $mail_config['recipient'] = "recipient@example.com";
    $mail_config['subject'] = "Veritabanlari Basarili Bir Sekilde Yedeklendi";
    $mail_config['text']  = "Veritabanlari basarili bir sekilde yedeklendi./n"
        . "Yedeklenen veritabanlari: ".  implode(", ", $database_names)."/n"
        . "Kaydedildigi sunucu: ".$mysql_configs['host']."/n"
        . "Kaydedildigi yol: ".$export_path."/n"
        . "Kaydedildigi isim: ".$export_name."/n";
    $mail_config['html']  = "Veritabanlari basarili bir sekilde yedeklendi.<br/>"
        . "Yedeklenen veritabanlari: ".  implode(", ", $database_names)."<br/>"
        . "Kaydedildigi sunucu: ".$mysql_configs['host']."<br/>"
        . "Kaydedildigi yol: ".$export_path."<br/>"
        . "Kaydedildigi isim: ".$export_name."<br/>";
    /**************************************************************************/
    /************************** Ayarlar - Bitti *******************************/
    /**************************************************************************/
    
    
    /*
     *  İşlemler...
     */
    $mysqldump_code = "\"C:\\Program Files\\MySQL\\MySQL Server 5.6\\bin\\mysqldump.exe\" ".
        "--opt --skip-extended-insert --complete-insert --host=".$mysql_configs['host']." --user=".$mysql_configs['username']." --password=".$mysql_configs['password'].
        " --databases ".  implode(" ", $database_names)." | gzip > ".$export_path.$export_name;

    system($mysqldump_code); // Dump komutunu çalıştır.

    echo implode(", ", $database_names). " veritaban(lar)inin yedegi ".$export_path." dizininde ".$export_name." olarak yedeklendi.\r\n";

    // FTPye dosyayı yükle...
    if($ftp_upload === TRUE) {
        try {
            $ftp_connect = ftp_connect($ftp_configs['host']);

            if(!$ftp_connect) {
                throw new Exception("FTP ile baglanti kurulamadi.\r\n");
            } else {
                try {
                    $ftp_login = ftp_login($ftp_connect, $ftp_configs['username'], $ftp_configs['password']);

                    if(!$ftp_login) {
                        throw new Exception("FTP oturum bilgileriniz yanlis.\r\n");
                    } else {
                        // FTP Login başarılı...
                        
                        if(ftp_put($ftp_connect, $ftp_remote_file, $ftp_file, FTP_BINARY)) {
                            // Dosya başarılı bir şekilde yüklendi.
                            echo "Veritaban(lar)i basarili bir sekilde FTPye yuklendi.\r\n";
                        } else {
                            // Dosya yükleme başarısız.
                            throw new Exception("Belirtilen dosya sunucuya yuklenemedi.\r\n");
                        }
                    }
                } catch (Exception $ex) {
                    echo "HATA ", $ex->getMessage(), "\r\n";
                }
            }
        } catch (Exception $ex) {
            echo "HATA ", $ex->getMessage(), "\r\n";
        }
        finally {
            ftp_close($ftp_connect);
        }
    }


    // Mail ile haberdar et.
    if($send_mail === TRUE) {
        include('Mail.php');
        include('Mail/mime.php');

        $crlf = "\n";
        $headers = array(
            'From'          => $mail_config['sender'],
            'Return-Path'   => $mail_config['sender'],
            'Subject'       => $mail_config['subject']
        );

        // Creating the Mime message
        $mime = new Mail_mime($crlf);

        // Setting the body of the email
        $mime->setTXTBody($mail_config['text']);
        $mime->setHTMLBody($mail_config['html']);

        $body = $mime->get();
        $header = $mime->headers($headers);

        // Sending the email
        $mail = Mail::factory('smtp', array(
            'host' => $mail_config['host'],
            'username' => $mail_config['username'],
            'password' => $mail_config['password'],
            'auth' => 'PLAIN'
        ));
        $mail->send($mail_config['recipient'], $header, $body);

        if (PEAR::isError($mail)) {
            echo "Mail gonderimi basarisiz.\r\n";
        } else {
            echo "Mail basarili bir sekilde gonderildi.\r\n";
        }
    }