-- Add vehicle make and model columns to transfers table
ALTER TABLE transfers
ADD COLUMN vehicle_make VARCHAR(100) DEFAULT NULL,
ADD COLUMN vehicle_model VARCHAR(100) DEFAULT NULL;

-- Note: Common car makes in Georgia include:
-- Toyota, Mercedes-Benz, BMW, Hyundai, Nissan, Lexus, Honda, Volkswagen, Audi, Subaru, etc.
