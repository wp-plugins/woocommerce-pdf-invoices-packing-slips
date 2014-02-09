<?php global $wpo_wcpdf; ?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<link href='http://fonts.googleapis.com/css?family=Open+Sans&subset=latin,latin-ext,cyrillic,cyrillic-ext' rel='stylesheet' type='text/css'>
	<title><?php echo ($wpo_wcpdf->export->template_type == 'invoice')?__( 'Invoice', 'wpo_wcpdf' ):__( 'Packing Slip', 'wpo_wcpdf' ) ?></title>
	<style><?php $wpo_wcpdf->template_styles(); ?></style>
</head>
<body>
<?php echo $wpo_wcpdf->export->output_body; ?>
</body>
</html>