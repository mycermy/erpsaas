````markdown
# Multi-Batch Allocation - Quick Reference

## The Question
**"How does the system handle sales when one batch doesn't have enough quantity?"**

## The Answer
The system **automatically allocates from multiple batches** using the `allocateBatches()` method.

## Example: Selling 50 Units (FIFO)

... (omitted) ...

## Summary

The multi-batch allocation system:
1. Automatically uses multiple batches when needed
2. Follows FIFO/LIFO rules correctly
3. Calculates accurate total cost
4. Reduces all involved batches
5. Creates one movement record (linked to primary batch)

**It just works!** 

````
