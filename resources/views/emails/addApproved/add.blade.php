@component('mail::message')
  <div style="background-color: #f9d9eb; padding:20px;">
        <p>Dear {{$user->name}} ,</p>
        <p>We're excited to let you know that your ad has been approved and is now live on Flexemarket! Congratulations on successfully listing your product on our platform.</p>
        <p>Your ad is now available for our community of buyers to view and purchase.</p>
        <p>To view your ad and manage your listings, please log in to your Flexemarket account and navigate to the "My Ads" section. From there, you can view your ad and make any necessary updates or changes.</p>
        <p>If you have any questions or concerns about your ad or your Flexemarket experience, please don't hesitate to reach out to our customer support team at <a href="mailto:support@flexemarket.com">support@flexemarket.com</a>.</p>
        <p>Thank you for being a valued member of the Flexemarket community. We wish you success in selling your products on our platform!</p>
        <p>Best regards,</p>
        <p><b>Flexemarket Support Team</b></p>
    </div>
@endcomponent