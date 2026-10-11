@include('errors.page', ['status' => 405, 'reference' => request()->attributes->get('homya.error_reference')])
