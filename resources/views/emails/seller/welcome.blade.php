@component('mail::message')
  <div style="background-color: #f9d9eb; padding:20px;">
        <p>Dear {{$user->name}},</p>
        <p>Thank you for registereing as Seller in NotNew.</p>
        <p>Thank you for choosing NotNew. We look forward to seeing your Product live on our site soon!</p>
        <p>Best regards,</p>
        <p><b>NotNew Support Team</b></p>
  </div>
@endcomponent