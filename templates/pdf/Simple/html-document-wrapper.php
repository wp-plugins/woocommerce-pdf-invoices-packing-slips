<?php global $wpo_wcpdf; ?>
<!DOCTYPE html>
<html class="invoice">
<head>
	<meta charset="utf-8">
	<title>Invoice</title>
	<style><?php $wpo_wcpdf->template_styles(); ?></style>
</head>
<body>
<?php echo $wpo_wcpdf->export->output_body; ?>
</body>
</html>