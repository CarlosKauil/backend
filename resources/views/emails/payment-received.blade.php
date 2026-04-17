@component('mail::message')
# ¡Pago confirmado! 🎉

Hola **{{ $auction->ganador->name }}**,

Tu pago por la obra **{{ $auction->obra->titulo }}** ha sido procesado exitosamente.

@component('mail::panel')
**Monto pagado:** ${{ number_format($auction->monto_ganador, 2) }} MXN  
**Transacción:** {{ $auction->transaccion_id }}  
**Fecha:** {{ $auction->fecha_pago?->format('d/m/Y H:i') }}
@endcomponent

Pronto nos pondremos en contacto para coordinar la entrega de tu obra.

Gracias por confiar en **Vartica**.

@endcomponent