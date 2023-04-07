<?php 

if(!function_exists('recovery_template')){
	function recovery_template($param = ''){
		$html = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<table class="body-wrap" style="width: 100%;margin: 0;padding: 0;box-sizing: border-box;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;-o-box-sizing:border-box">
			<tr>
				<td style="vertical-align: top;"  style="vertical-align: top;"></td>
				<td style="vertical-align: top;"  class="container" width="600" style="vertical-align: top;">
					<div class="content">
						<table class="main" width="100%" style="background:#f6f6f6;" cellpadding="0" cellspacing="0">
							<tr>
								<td style="vertical-align: top;padding: 20px"  class="content-wrap" >
									<table  cellpadding="0" cellspacing="0">
										<tr>
											<td style="vertical-align: top;padding: 0 0 20px;"  class="content-block">
												<h3 style="margin-top:10px !important;margin-top:10px;font-family:\'Segoe UI\';font-weight:500;text-transform:uppercase;">Xin chào '.$param['fullname'].',</h3>
											</td>
										</tr>
										<tr>
											<td style="vertical-align: top;padding:0 0 20px 0;font-family:\'Segoe UI\' !important;font-size:15px;line-height:168%;"  class="content-block">
												Chúng tôi nhận thấy bạn gặp sự cố khi đăng nhập tài khoản. Nếu bạn cần cài đặt lại mật khẩu của mình, hãy làm theo hướng bên dưới và chúng tôi sẽ giúp bạn đăng nhập.
											</td>
										</tr>
										<tr>
											<td style="vertical-align: top;padding:0 0 20px 0;font-family:\'Segoe UI\' !important;font-size:15px;line-height:168%;"  class="content-block"  class="content-block">
												Đã có hoạt động đăng nhập không thành công vào tài khoản của bạn. Sử dụng mã xác thực dưới đây để chắc chắn rằng là bạn đang thực hiện thao tác này.
											</td>
										</tr>
										<tr>
											<td style="vertical-align: top;"  class="content-block aligncenter">
												<a href="#" class="btn-primary" style="text-decoration: none;color: #FFF;background-color: #1ab394;border: solid #1ab394;border-width: 5px 10px;line-height:2;font-weight:500;font-family:\'Segoe UI\';text-align:center;display:inline-block;">'.((isset($param['otpCode'])) ? $param['otpCode'] : '').'</a>
											</td>
										</tr>
									  </table>
								</td>
							</tr>
						</table>
						<div class="footer" style="margin-top:10px;">
							<table width="100%">
								<tr>
									<td style="vertical-align: top;font-family:\'Segoe UI\';font-size:15px;font-style:italic;"  class="aligncenter content-block"><p style="margin:0 0 2px 0;font-size:11px;color:#aaaaaa">Tin nhắn này đã được gửi tới <a	 href="mailto:tuannc.dev@gmail.com" style="text-decoration:none;">tuannc.dev@gmail.com</a>.</p> <p style="margin:0 0 2px 0;font-size:11px;color:#aaaaaa">HT Viet Nam , Số 80 Mễ Trì Hạ, Hà Nội</p><p style="margin:0 0 2px 0;font-size:11px;color:#aaaaaa">Vui lòng không chuyển tiếp email này để giữ cho tài khoản của bạn an toàn.</p></td>
								</tr>
							</table>
						</div></div>
				</td>
				<td style="vertical-align: top;"  style="vertical-align: top;" ></td>
			</tr>
		</table>';
		return $html;
	}
}