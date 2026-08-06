<?php

/*
|--------------------------------------------------------------------------
| Workshop intake questions
|--------------------------------------------------------------------------
|
| Ported from the Arabic Google Form. This is the server-side source of truth:
| it drives validation, the dashboard's answer rendering, and the confirmation
| email. It must stay in step with the frontend's
| `src/data/reservationQuestions.js`.
|
| Versioned on purpose. When the questions change, add a new version key and
| bump Reservation::question_set_version for new submissions — existing rows
| keep rendering against the set that produced them.
|
*/

return [

    'current' => 'v1',

    'v1' => [
        [
            'id' => 'name',
            'type' => 'text',
            'label' => 'Full name',
            'ar' => 'الأسم',
            'required' => true,
            'column' => 'name',
        ],
        [
            'id' => 'gender',
            'type' => 'radio',
            'label' => 'Gender',
            'ar' => 'الجنس',
            'required' => true,
            'column' => 'gender',
            'options' => [
                ['value' => 'male', 'label' => 'Male', 'ar' => 'ذكر'],
                ['value' => 'female', 'label' => 'Female', 'ar' => 'أنثى'],
            ],
        ],
        [
            'id' => 'age',
            'type' => 'number',
            'label' => 'Age',
            'ar' => 'العمر',
            'required' => true,
            'column' => 'age',
            'min' => 10,
            'max' => 100,
        ],
        [
            'id' => 'phone',
            'type' => 'tel',
            'label' => 'Phone number',
            'ar' => 'رقم الهاتف',
            'required' => true,
            'column' => 'phone',
        ],
        [
            'id' => 'email',
            'type' => 'email',
            'label' => 'Email address',
            'ar' => 'البريد الالكتروني',
            'required' => false,
            'column' => 'email',
        ],
        [
            'id' => 'worksCommercially',
            'type' => 'radio',
            'label' => 'Do you currently work in commercial photography (weddings, advertising, products, etc.)?',
            'ar' => 'هل تعمل حالياً في مجال التصوير التجاري (مثل الأعراس، الإعلانات، المنتجات، إلخ)؟',
            'required' => true,
            'options' => [
                ['value' => 'yes', 'label' => 'Yes', 'ar' => 'نعم'],
                ['value' => 'no', 'label' => 'No', 'ar' => 'لا'],
            ],
        ],
        [
            'id' => 'specialities',
            'type' => 'checkbox',
            'label' => 'Which areas do you work in or care about?',
            'ar' => 'ما هي التخصصات التي تعمل/تهتم بها في التصوير؟ (يمكن اختيار أكثر من خيار)',
            'required' => true,
            'options' => [
                ['value' => 'weddings', 'label' => 'Weddings', 'ar' => 'تصوير الاعراس'],
                ['value' => 'products', 'label' => 'Products', 'ar' => 'تصوير المنتجات'],
                ['value' => 'portrait', 'label' => 'Portrait', 'ar' => 'تصوير البورترية'],
                ['value' => 'travel', 'label' => 'Travel & nature', 'ar' => 'تصوير السفر والطبيعة'],
                ['value' => 'video', 'label' => 'Video', 'ar' => 'تصوير الفيديو'],
                ['value' => 'photojournalism', 'label' => 'Photojournalism', 'ar' => 'التصوير الصحفي'],
                ['value' => 'other', 'label' => 'Other', 'ar' => 'اخرى'],
            ],
        ],
        [
            'id' => 'device',
            'type' => 'radio',
            'label' => 'What do you mainly shoot on?',
            'ar' => 'ما نوع الجهاز الأساسي الذي تستخدمه في التصوير؟',
            'required' => true,
            'options' => [
                ['value' => 'camera', 'label' => 'A professional camera (DSLR or mirrorless)', 'ar' => 'كاميرا احترافية (DSLR أو Mirrorless)'],
                ['value' => 'phone', 'label' => 'A mobile phone', 'ar' => 'هاتف نقال'],
                ['value' => 'both', 'label' => 'Both', 'ar' => 'الاثنان معاً'],
            ],
        ],
        [
            'id' => 'brand',
            'type' => 'radio',
            'label' => 'Which system do you rely on most?',
            'ar' => 'على ماذا تعتمد بشكل رئيسي؟',
            'required' => true,
            'options' => [
                ['value' => 'sony', 'label' => 'Sony'],
                ['value' => 'canon', 'label' => 'Canon'],
                ['value' => 'nikon', 'label' => 'Nikon'],
                ['value' => 'fujifilm', 'label' => 'Fujifilm'],
                ['value' => 'phone', 'label' => 'Phone'],
            ],
        ],
        [
            'id' => 'hasLighting',
            'type' => 'radio',
            'label' => 'Do you own lighting equipment?',
            'ar' => 'هل تمتلك معدات إضاءة؟',
            'required' => true,
            'options' => [
                ['value' => 'yes', 'label' => 'Yes', 'ar' => 'نعم'],
                ['value' => 'no', 'label' => 'No', 'ar' => 'لا'],
            ],
        ],
        [
            'id' => 'motivation',
            'type' => 'textarea',
            'label' => 'Why do you want to join this workshop?',
            'ar' => 'لماذا تود الدخول لهذه الورشة؟',
            'required' => true,
        ],
    ],

];
