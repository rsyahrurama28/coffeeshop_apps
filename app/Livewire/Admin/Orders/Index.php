<?php

namespace App\Livewire\Admin\Orders;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Order;

class Index extends Component
{
    use WithPagination;

    public string $filterStatus = 'all';
    public ?int $latestOrderId = null;

    public function mount()
    {
        $this->latestOrderId = Order::where('payment_status', 'Paid')
            ->latest()->value('id');
    }

    // Polling tiap 4 detik — cek apakah ada order baru
    public function refreshOrders()
    {
        $newest = Order::where('payment_status', 'Paid')->latest()->value('id');

        if ($newest && $newest > ($this->latestOrderId ?? 0)) {
            $this->latestOrderId = $newest;
            $this->dispatch('new-order-arrived');
        }
    }

    public function updateStatus(int $orderId, string $status)
    {
        abort_unless(in_array($status, ['Pending', 'Processing', 'Completed']), 422);
        Order::findOrFail($orderId)->update(['order_status' => $status]);
        $this->dispatch('status-updated', orderId: $orderId);
    }

    public function setFilter(string $status)
    {
        $this->filterStatus = $status;
        $this->resetPage();
    }

    public function getOrdersProperty()
    {
        return Order::with(['table', 'items.menu', 'items.addons.addOn'])
            ->where('payment_status', 'Paid')
            ->when($this->filterStatus !== 'all', fn($q) => $q->where('order_status', ucfirst($this->filterStatus)))
            ->orderByRaw("FIELD(order_status, 'Pending', 'Processing', 'Completed')")
            ->latest()
            ->paginate(12);
    }

    public function getCounts(): array
    {
        $base = Order::where('payment_status', 'Paid');
        return [
            'all'        => (clone $base)->count(),
            'pending'    => (clone $base)->where('order_status', 'Pending')->count(),
            'processing' => (clone $base)->where('order_status', 'Processing')->count(),
            'completed'  => (clone $base)->where('order_status', 'Completed')->count(),
        ];
    }

    public function render()
    {
        return view('livewire.admin.orders.index', [
            'orders' => $this->orders,
            'counts' => $this->getCounts(),
        ])->with('layouts.app', ['title' => 'Live Orders — Warso Coffee']);
    }
}
