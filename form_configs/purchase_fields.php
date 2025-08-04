// form_configs/purchase_fields.php
return [
    'sections' => [
        [
            'title' => 'פרטי הרוכש',
            'fields' => [
                [
                    'name' => 'buyer_type',
                    'label' => 'סוג רוכש',
                    'type' => 'select',
                    'options' => [
                        'individual' => 'אדם פרטי',
                        'company' => 'חברה'
                    ],
                    'col_class' => 'col-md-6',
                    'required' => true
                ],
                [
                    'name' => 'buyer_id_type',
                    'label' => 'סוג זיהוי',
                    'type' => 'select',
                    'options' => [
                        'tz' => 'תעודת זהות',
                        'passport' => 'דרכון',
                        'company_id' => 'ח.פ.'
                    ],
                    'col_class' => 'col-md-6',
                    'required' => true
                ],
                [
                    'name' => 'buyer_id_number',
                    'label' => 'מספר זיהוי',
                    'type' => 'text',
                    'col_class' => 'col-md-6',
                    'required' => true
                ],
                [
                    'name' => 'buyer_name',
                    'label' => 'שם מלא',
                    'type' => 'text',
                    'col_class' => 'col-md-6',
                    'required' => true
                ],
                [
                    'name' => 'buyer_phone',
                    'label' => 'טלפון',
                    'type' => 'tel',
                    'col_class' => 'col-md-4'
                ],
                [
                    'name' => 'buyer_email',
                    'label' => 'דוא"ל',
                    'type' => 'email',
                    'col_class' => 'col-md-4'
                ],
                [
                    'name' => 'buyer_address',
                    'label' => 'כתובת',
                    'type' => 'textarea',
                    'col_class' => 'col-md-4'
                ]
            ]
        ],
        [
            'title' => 'פרטי הרכישה',
            'fields' => [
                [
                    'name' => 'purchase_type',
                    'label' => 'סוג רכישה',
                    'type' => 'select',
                    'options' => [
                        'grave' => 'קבר בודד',
                        'plot' => 'חלקת קבורה',
                        'structure' => 'מבנה',
                        'service' => 'שירות'
                    ],
                    'col_class' => 'col-md-4',
                    'required' => true
                ],
                [
                    'name' => 'purchase_date',
                    'label' => 'תאריך רכישה',
                    'type' => 'date',
                    'col_class' => 'col-md-4',
                    'required' => true
                ],
                [
                    'name' => 'total_amount',
                    'label' => 'סכום כולל',
                    'type' => 'number',
                    'step' => '0.01',
                    'col_class' => 'col-md-4',
                    'required' => true
                ]
            ]
        ]
    ]
];